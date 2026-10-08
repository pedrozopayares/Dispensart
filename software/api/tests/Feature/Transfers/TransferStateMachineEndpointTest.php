<?php

use App\Enums\TransferStatus;
use App\Models\Lot;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// transfers "Tabla de transiciones del traslado" por HTTP real y Escrituras protegidas «Sin sesión». Actor por
// acción (design D15), siempre con el permiso y pasando la Policy, para que el único rechazo posible sea el 409:
// solicitar → el creador; aprobar → regente distinto del creador; despachar y recibir → auxiliar; anular → regente.
// Las 28 combinaciones prohibidas se escriben aquí a mano, nunca derivadas de la tabla que se prueba.

const FORBIDDEN_TRANSITIONS = [
    'BORRADOR' => ['approve', 'dispatch', 'receive'],
    'SOLICITADO' => ['request', 'dispatch', 'receive'],
    'APROBADO' => ['request', 'approve', 'receive'],
    'EN_TRANSITO' => ['request', 'approve', 'dispatch', 'void'],
    'RECIBIDO' => ['request', 'approve', 'dispatch', 'receive', 'void'],
    'RECIBIDO_PARCIAL' => ['request', 'approve', 'dispatch', 'receive', 'void'],
    'ANULADO' => ['request', 'approve', 'dispatch', 'receive', 'void'],
];

dataset('forbidden_transitions', function () {
    $rows = [];
    foreach (FORBIDDEN_TRANSITIONS as $status => $actions) {
        foreach ($actions as $action) {
            $rows["{$action} desde {$status}"] = [TransferStatus::from($status), $action];
        }
    }

    return $rows;
});

beforeEach(function () {
    $this->creator = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
});

/**
 * Cuerpo válido de cada acción.
 *
 * @return array<string, mixed>
 */
function validActionBody(Transfer $transfer, string $action): array
{
    return match ($action) {
        'receive' => receiveBody($transfer),
        'void' => ['reason' => 'Motivo válido'],
        default => [],
    };
}

it('declara exactamente 28 combinaciones prohibidas', function () {
    expect(array_sum(array_map('count', FORBIDDEN_TRANSITIONS)))->toBe(28);
});

it('rechaza cada transición prohibida con 409 sin cambiar estado, existencias, kardex ni bitácora', function (TransferStatus $status, string $action) {
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 2]], $status, ['created_by' => $this->creator->id]);
    // Existencia suficiente en origen: un despacho indebido tendría con qué salir.
    originStock($transfer, $lot, 10);
    $actor = match ($action) {
        'request', 'dispatch', 'receive' => $this->creator,
        'approve', 'void' => $this->regente,
    };
    $before = transferState();

    transferAction($actor, $transfer, $action, validActionBody($transfer, $action))
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_transfer_transition')
        ->assertJsonPath('message', __('errors.invalid_transfer_transition'));

    expect(transferState())->toBe($before);
})->with('forbidden_transitions');

it('recorre BORRADOR → SOLICITADO → APROBADO → EN_TRANSITO → RECIBIDO con HTTP 200 en cada paso', function () {
    $lot = Lot::factory()->create();
    $origin = Warehouse::factory()->create();
    $destination = Warehouse::factory()->create();
    stockOf(5, ['warehouse_id' => $origin->id, 'lot_id' => $lot->id]);

    $created = $this->actingAs($this->creator)->postJson('/api/transfers', [
        'origin_warehouse_id' => $origin->id, 'destination_warehouse_id' => $destination->id,
        'lines' => [['lot_id' => $lot->id, 'quantity' => 2]],
    ])->assertCreated();
    $transfer = Transfer::findOrFail($created->json('data.id'));
    $statuses = [$created->json('data.status')];
    $statuses[] = transferAction($this->creator, $transfer, 'request')->assertOk()->json('data.status');
    $statuses[] = transferAction($this->regente, $transfer, 'approve')->assertOk()->json('data.status');
    $statuses[] = transferAction($this->creator, $transfer, 'dispatch')->assertOk()->json('data.status');
    $statuses[] = transferAction($this->creator, $transfer, 'receive', receiveBody($transfer))->assertOk()->json('data.status');

    expect($statuses)->toBe(['BORRADOR', 'SOLICITADO', 'APROBADO', 'EN_TRANSITO', 'RECIBIDO']);
});

it('rechaza con 409 las 15 acciones sobre estados terminales: R1 creador y solicitante, R2 aprueba', function (TransferStatus $status) {
    $r1 = $this->regente;
    $r2 = User::factory()->regente()->create();
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 2]], $status, ['created_by' => $r1->id]);
    originStock($transfer, $lot, 10);
    $before = transferState();

    $codes = [];
    foreach (['request' => $r1, 'approve' => $r2, 'dispatch' => $r1, 'receive' => $r1, 'void' => $r1] as $action => $actor) {
        $response = transferAction($actor, $transfer, $action, validActionBody($transfer, $action));
        $codes[$action] = [$response->status(), $response->json('code')];
    }

    expect($codes)->toBe(array_fill_keys(['request', 'approve', 'dispatch', 'receive', 'void'], [409, 'invalid_transfer_transition']))
        ->and(transferState())->toBe($before);
})->with(['RECIBIDO' => [TransferStatus::Received], 'RECIBIDO_PARCIAL' => [TransferStatus::PartiallyReceived], 'ANULADO' => [TransferStatus::Voided]]);

it('evalúa el permiso antes que el estado: el auditor despacha un RECIBIDO y recibe 403', function () {
    $transfer = transferWith([[Lot::factory()->create(), 2]], TransferStatus::Received);

    transferAction(User::factory()->auditor()->create(), $transfer, 'dispatch')->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('responde 401 sin sesión en las 7 escrituras sin cambiar traslados ni movimientos', function () {
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 2]], TransferStatus::PartiallyReceived);
    $line = $transfer->lines->sole();
    $discrepancy = (new TransferDiscrepancy)->forceFill(['transfer_id' => $transfer->id, 'transfer_line_id' => $line->id, 'shortage' => 1, 'status' => 'pending']);
    $discrepancy->save();
    $before = transferState();

    $writes = [
        ['/api/transfers', ['origin_warehouse_id' => $transfer->origin_warehouse_id, 'destination_warehouse_id' => $transfer->destination_warehouse_id, 'lines' => [['lot_id' => $lot->id, 'quantity' => 1]]]],
        ["/api/transfers/{$transfer->id}/request", []],
        ["/api/transfers/{$transfer->id}/approve", []],
        ["/api/transfers/{$transfer->id}/dispatch", []],
        ["/api/transfers/{$transfer->id}/receive", receiveBody($transfer)],
        ["/api/transfers/{$transfer->id}/void", ['reason' => 'x']],
        ["/api/transfers/{$transfer->id}/discrepancies/{$discrepancy->id}/resolve", ['resolution' => 'written_off', 'reason' => 'x']],
    ];
    $results = [];
    foreach ($writes as [$uri, $body]) {
        $response = $this->postJson($uri, $body);
        $results[] = [$response->status(), $response->json('code')];
    }

    expect($results)->toBe(array_fill(0, 7, [401, 'unauthenticated']))
        ->and(transferState())->toBe($before);
});
