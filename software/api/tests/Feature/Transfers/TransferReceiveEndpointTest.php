<?php

use App\Enums\Role;
use App\Enums\TransferStatus;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// transfers "Recepción del traslado" (salvo la carrera) y Movimientos («Trazabilidad en el kardex», «Balance de un
// traslado parcial»): POST /api/transfers/{id}/receive por HTTP real.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->lotA = Lot::factory()->create();
    $this->lotB = Lot::factory()->create();
    $this->transfer = transferWith([[$this->lotA, 3], [$this->lotB, 2]], TransferStatus::InTransit);
});

it('recibe completo: RECIBIDO, sin discrepancias, destino suma con un entrada_traslado por línea', function () {
    stockOf(4, ['warehouse_id' => $this->transfer->destination_warehouse_id, 'lot_id' => $this->lotA->id]);

    transferAction($this->auxiliar, $this->transfer, 'receive', receiveBody($this->transfer, [3, 2]))
        ->assertOk()
        ->assertJsonPath('data.status', 'RECIBIDO')
        ->assertJsonPath('data.received_by.id', $this->auxiliar->id)
        ->assertJsonPath('data.discrepancies', [])
        ->assertJsonPath('data.lines.0.received_quantity', 3);

    $destination = $this->transfer->destination_warehouse_id;
    expect([stockAt($destination, $this->lotA)?->quantity, stockAt($destination, $this->lotB)?->quantity])->toBe([7, 2]);
    $inbound = KardexMovement::query()->where('type', 'entrada_traslado')->orderBy('id')->get();
    expect($inbound->map(fn ($m) => [$m->warehouse_id, $m->lot_id, $m->quantity, $m->user_id])->all())->toBe([
        [$destination, $this->lotA->id, 3, $this->auxiliar->id],
        [$destination, $this->lotB->id, 2, $this->auxiliar->id],
    ]);
});

it('crea la existencia en destino cuando no había, con balance_after igual a lo recibido', function () {
    expect(stockAt($this->transfer->destination_warehouse_id, $this->lotB))->toBeNull();

    transferAction($this->auxiliar, $this->transfer, 'receive', receiveBody($this->transfer))->assertOk();

    expect(stockAt($this->transfer->destination_warehouse_id, $this->lotB)?->quantity)->toBe(2)
        ->and(KardexMovement::query()->where('type', 'entrada_traslado')->where('lot_id', $this->lotB->id)->sole()->balance_after)->toBe(2);
});

it('recibe parcial: RECIBIDO_PARCIAL con una sola discrepancia pendiente de faltante 1 en la primera línea', function () {
    $response = transferAction($this->auxiliar, $this->transfer, 'receive', receiveBody($this->transfer, [2, 2]));

    $response->assertOk()
        ->assertJsonPath('data.status', 'RECIBIDO_PARCIAL')
        ->assertJsonCount(1, 'data.discrepancies')
        ->assertJsonPath('data.discrepancies.0.line_id', $this->transfer->lines[0]->id)
        ->assertJsonPath('data.discrepancies.0.shortage', 1)
        ->assertJsonPath('data.discrepancies.0.status', 'pending');
    $destination = $this->transfer->destination_warehouse_id;
    expect([stockAt($destination, $this->lotA)?->quantity, stockAt($destination, $this->lotB)?->quantity])->toBe([2, 2])
        ->and(TransferDiscrepancy::count())->toBe(1);
});

it('cuadra el balance de un parcial: salida -5, entrada 3 y faltante 2', function () {
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 5]], TransferStatus::Approved);
    originStock($transfer, $lot, 5);
    transferAction($this->auxiliar, $transfer, 'dispatch')->assertOk();

    transferAction($this->auxiliar, $transfer, 'receive', receiveBody($transfer, [3]))->assertOk();

    $line = $transfer->lines()->sole();
    expect(KardexMovement::findOrFail($line->dispatch_movement_id)->quantity)->toBe(-5)
        ->and(KardexMovement::findOrFail($line->receipt_movement_id)->quantity)->toBe(3)
        ->and(TransferDiscrepancy::where('transfer_line_id', $line->id)->sole()->shortage)->toBe(2);
});

it('no recibe nada: RECIBIDO_PARCIAL sin entradas y con discrepancias de 3 y 2', function () {
    transferAction($this->auxiliar, $this->transfer, 'receive', receiveBody($this->transfer, [0, 0]))
        ->assertOk()
        ->assertJsonPath('data.status', 'RECIBIDO_PARCIAL')
        ->assertJsonPath('data.discrepancies.*.shortage', [3, 2]);

    expect(movementsOfType('entrada_traslado'))->toBe(0);
});

it('rechaza la sobre-recepción con 422 en el received_quantity de la línea, sin efecto', function () {
    $before = transferState();

    transferAction($this->auxiliar, $this->transfer, 'receive', receiveBody($this->transfer, [4, 2]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('lines.0.received_quantity');

    expect(transferState())->toBe($before);
});

it('rechaza una recepción incompleta o mal formada sin movimiento ni discrepancia', function (Closure $body, string $field) {
    $before = transferState();
    $foreign = transferWith([[Lot::factory()->create(), 1]], TransferStatus::InTransit);

    transferAction($this->auxiliar, $this->transfer, 'receive', $body($this->transfer, $foreign))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($field);

    $after = transferState();
    expect([$after['kardex'], $after['discrepancies'], $after['transfers'][$this->transfer->id]])
        ->toBe([$before['kardex'], $before['discrepancies'], $before['transfers'][$this->transfer->id]]);
})->with([
    'línea omitida' => [fn ($t) => ['lines' => [receiveBody($t)['lines'][0]]], 'lines'],
    'línea de otro traslado' => [fn ($t, $f) => ['lines' => [receiveBody($t)['lines'][0], receiveBody($f)['lines'][0]]], 'lines.1.line_id'],
    'línea repetida' => [fn ($t) => ['lines' => [receiveBody($t)['lines'][0], receiveBody($t)['lines'][0]]], 'lines.1.line_id'],
    'recibido -1' => [fn ($t) => receiveBody($t, [-1, 2]), 'lines.0.received_quantity'],
    'recibido 1.5' => [fn ($t) => receiveBody($t, [1.5, 2]), 'lines.0.received_quantity'],
    'sin lines' => [fn () => [], 'lines'],
]);

it('recibe completo un lote que venció en tránsito y lo muestra vencido', function () {
    $lot = Lot::factory()->create(['expires_on' => BusinessCalendar::today()->toDateString()]);
    $transfer = transferWith([[$lot, 2]], TransferStatus::InTransit);

    transferAction($this->auxiliar, $transfer, 'receive', receiveBody($transfer))
        ->assertOk()
        ->assertJsonPath('data.status', 'RECIBIDO')
        ->assertJsonPath('data.lines.0.lot.is_expired', true);
    expect(stockAt($transfer->destination_warehouse_id, $lot)?->quantity)->toBe(2);
});

it('rechaza repetir la recepción de un RECIBIDO_PARCIAL con 409 sin nuevas entradas ni discrepancias', function () {
    transferAction($this->auxiliar, $this->transfer, 'receive', receiveBody($this->transfer, [2, 2]))->assertOk();
    $inbound = movementsOfType('entrada_traslado');

    transferAction($this->auxiliar, $this->transfer, 'receive', receiveBody($this->transfer, [2, 2]))
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_transfer_transition');

    expect(movementsOfType('entrada_traslado'))->toBe($inbound)->and(TransferDiscrepancy::count())->toBe(1);
});

it('rechaza con 403 a los roles sin recepción y sigue EN_TRANSITO', function (Role $role) {
    transferAction(User::factory()->withRole($role)->create(), $this->transfer, 'receive', receiveBody($this->transfer))
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    expect($this->transfer->fresh()?->status)->toBe(TransferStatus::InTransit);
})->with(['medico' => [Role::Medico], 'auditor' => [Role::Auditor], 'admin' => [Role::Admin]]);

it('deja en el kardex del lote un salida_traslado en origen y un entrada_traslado en destino con usuario y traslado', function () {
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 2]], TransferStatus::Approved);
    originStock($transfer, $lot, 5);
    $receiver = User::factory()->auxiliar()->create();
    transferAction($this->auxiliar, $transfer, 'dispatch')->assertOk();
    transferAction($receiver, $transfer, 'receive', receiveBody($transfer))->assertOk();

    $response = $this->actingAs(User::factory()->auditor()->create())->getJson("/api/kardex?lot_id={$lot->id}")->assertOk();

    $byType = collect($response->json('data'))->keyBy('type');
    expect($byType['salida_traslado']['warehouse']['id'])->toBe($transfer->origin_warehouse_id)
        ->and($byType['salida_traslado']['user']['id'])->toBe($this->auxiliar->id)
        ->and($byType['salida_traslado']['reason'])->toContain((string) $transfer->id)
        ->and($byType['entrada_traslado']['warehouse']['id'])->toBe($transfer->destination_warehouse_id)
        ->and($byType['entrada_traslado']['user']['id'])->toBe($receiver->id)
        ->and($byType['entrada_traslado']['reason'])->toContain((string) $transfer->id);
});
