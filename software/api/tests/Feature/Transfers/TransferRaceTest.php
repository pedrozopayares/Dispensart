<?php

use App\Enums\TransferStatus;
use App\Models\AuditEvent;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RaceRunner;

// transfers «Despacho contra dispensación por la última unidad», «Despachos simultáneos del mismo traslado»,
// «Recepciones simultáneas» y «Resoluciones simultáneas» (RN-03, RN-06, RN-07; design D13). Procesos reales con
// conexiones propias y kernel HTTP completo (RaceRunner): nada de RefreshDatabase; DatabaseMigrations migra al
// entrar y revierte al salir (ejerce el down() NOT VALID de la bitácora con filas transfer.* presentes). Filas
// nuevas por iteración.

uses(DatabaseMigrations::class);

const TRANSFER_RACE_ITERATIONS = 10;

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
});

/**
 * @param  array<string, mixed>  $body
 * @return array{uri: string, user_id: int, body: array<string, mixed>}
 */
function transferRaceRequest(User $user, Transfer $transfer, string $action, array $body = []): array
{
    return ['uri' => "/api/transfers/{$transfer->id}/{$action}", 'user_id' => $user->id, 'body' => $body];
}

/**
 * @param  list<array{status: int, code: string|null, body: array<string, mixed>|null}>  $results
 * @return array{statuses: list<int>, codes: list<string>}
 */
function raceSummary(array $results): array
{
    $statuses = array_column($results, 'status');
    sort($statuses);

    return ['statuses' => $statuses, 'codes' => array_values(array_filter(array_column($results, 'code')))];
}

/**
 * @param  array<int, array<string, mixed>>  $outcomes
 * @param  array<int, array<string, mixed>>  $expected
 */
function expectEveryTransferIteration(array $outcomes, array $expected): void
{
    $failed = array_filter($outcomes, fn (array $outcome, int $i) => $outcome !== $expected[$i], ARRAY_FILTER_USE_BOTH);
    expect($failed)->toBe([], count($failed).'/'.TRANSFER_RACE_ITERATIONS.' iteraciones fuera de lo esperado');
}

it('serializa despacho contra dispensación por la última unidad, primero alternado: uno gana, el otro 409, 10 de 10', function () {
    $outcomes = [];
    $expected = [];
    for ($i = 1; $i <= TRANSFER_RACE_ITERATIONS; $i++) {
        $origin = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $stock = lotStock($origin, $product, 30, 1);
        $lot = Lot::findOrFail($stock->lot_id);
        $transfer = transferWith([[$lot, 1]], TransferStatus::Approved, ['origin_warehouse_id' => $origin->id]);
        $prescription = prescriptionWith([[$product, 5]]);
        $dispensation = [
            'uri' => '/api/dispensations', 'user_id' => $this->regente->id,
            'body' => dispensationBody($prescription, $origin, [[$product, 1]]),
            'headers' => ['Idempotency-Key' => newIdempotencyKey()],
        ];
        $dispatch = transferRaceRequest($this->auxiliar, $transfer, 'dispatch');
        $dispensationFirst = $i % 2 === 1;
        $kardexBefore = KardexMovement::count();

        // Arranque escalonado (design D13): el primero ya espera en la barrera cuando sale el segundo.
        [$first, $second] = RaceRunner::staggered($dispensationFirst ? [$dispensation, $dispatch] : [$dispatch, $dispensation]);
        [$dispensed, $dispatched] = $dispensationFirst ? [$first, $second] : [$second, $first];

        $outcomes[$i] = [
            'dispensation' => [$dispensed['status'], $dispensed['code']],
            'dispatch' => [$dispatched['status'], $dispatched['code']],
            'quantity' => $stock->fresh()?->quantity,
            'new_movements' => KardexMovement::count() - $kardexBefore,
            'transfer' => $transfer->fresh()?->status->value,
        ];
        $expected[$i] = $dispensationFirst
            ? ['dispensation' => [201, null], 'dispatch' => [409, 'insufficient_stock'], 'quantity' => 0, 'new_movements' => 1, 'transfer' => 'APROBADO']
            : ['dispensation' => [409, 'insufficient_stock'], 'dispatch' => [200, null], 'quantity' => 0, 'new_movements' => 1, 'transfer' => 'EN_TRANSITO'];
    }

    expectEveryTransferIteration($outcomes, $expected);
    expect(KardexMovement::where('balance_after', '<', 0)->count())->toBe(0);
});

it('serializa dos despachos simultáneos del mismo traslado: un 200, un 409 y una sola salida, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= TRANSFER_RACE_ITERATIONS; $i++) {
        $lot = Lot::factory()->create();
        $transfer = transferWith([[$lot, 3]], TransferStatus::Approved);
        // Existencia ≥ 2 × cantidad: un segundo despacho sin bloqueo del traslado tendría con qué salir (M8).
        $stock = originStock($transfer, $lot, 10);

        $results = RaceRunner::post("/api/transfers/{$transfer->id}/dispatch", [
            ['user_id' => $this->auxiliar->id, 'body' => []],
            ['user_id' => $this->auxiliar->id, 'body' => []],
        ]);

        $outcomes[$i] = [
            ...raceSummary($results),
            'quantity' => $stock->fresh()?->quantity,
            'outbound' => KardexMovement::where('lot_id', $lot->id)->where('type', 'salida_traslado')->count(),
            'transfer' => $transfer->fresh()?->status->value,
        ];
    }

    expectEveryTransferIteration($outcomes, array_fill(1, TRANSFER_RACE_ITERATIONS, [
        'statuses' => [200, 409], 'codes' => ['invalid_transfer_transition'], 'quantity' => 7, 'outbound' => 1, 'transfer' => 'EN_TRANSITO',
    ]));
});

it('serializa dos recepciones parciales simultáneas: un 200, un 409, una entrada por línea y una discrepancia, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= TRANSFER_RACE_ITERATIONS; $i++) {
        $a = Lot::factory()->create();
        $b = Lot::factory()->create();
        $transfer = transferWith([[$a, 3], [$b, 2]], TransferStatus::InTransit);
        $body = receiveBody($transfer, [3, 1]);

        $results = RaceRunner::post("/api/transfers/{$transfer->id}/receive", [
            ['user_id' => $this->auxiliar->id, 'body' => $body],
            ['user_id' => $this->auxiliar->id, 'body' => $body],
        ]);

        $destination = $transfer->destination_warehouse_id;
        $outcomes[$i] = [
            ...raceSummary($results),
            'inbound' => KardexMovement::whereIn('lot_id', [$a->id, $b->id])->where('type', 'entrada_traslado')->count(),
            'discrepancies' => TransferDiscrepancy::where('transfer_id', $transfer->id)->count(),
            'destination' => [stockAt($destination, $a)?->quantity, stockAt($destination, $b)?->quantity],
            'transfer' => $transfer->fresh()?->status->value,
        ];
    }

    expectEveryTransferIteration($outcomes, array_fill(1, TRANSFER_RACE_ITERATIONS, [
        'statuses' => [200, 409], 'codes' => ['invalid_transfer_transition'], 'inbound' => 2, 'discrepancies' => 1,
        'destination' => [3, 1], 'transfer' => 'RECIBIDO_PARCIAL',
    ]));
});

it('serializa dos resoluciones simultáneas de la misma discrepancia: un 200, un 409 y un solo ajuste, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= TRANSFER_RACE_ITERATIONS; $i++) {
        $lot = Lot::factory()->create();
        $transfer = transferWith([[$lot, 3]], TransferStatus::PartiallyReceived);
        $line = $transfer->lines->sole();
        $line->forceFill(['received_quantity' => 2])->save();
        $discrepancy = (new TransferDiscrepancy)->forceFill([
            'transfer_id' => $transfer->id, 'transfer_line_id' => $line->id, 'shortage' => 1, 'status' => 'pending',
        ]);
        $discrepancy->save();
        $stock = originStock($transfer, $lot, 5);
        $body = ['resolution' => 'returned_to_origin', 'reason' => "Carrera {$i}"];

        $results = RaceRunner::post("/api/transfers/{$transfer->id}/discrepancies/{$discrepancy->id}/resolve", [
            ['user_id' => $this->regente->id, 'body' => $body],
            ['user_id' => $this->regente->id, 'body' => $body],
        ]);

        $outcomes[$i] = [
            ...raceSummary($results),
            'adjustments' => KardexMovement::where('lot_id', $lot->id)->where('type', 'ajuste')->count(),
            'quantity' => $stock->fresh()?->quantity,
            'audit' => AuditEvent::where('action', 'transfer.discrepancy_resolved')->where('subject_id', $transfer->id)->count(),
        ];
    }

    expectEveryTransferIteration($outcomes, array_fill(1, TRANSFER_RACE_ITERATIONS, [
        'statuses' => [200, 409], 'codes' => ['discrepancy_already_resolved'], 'adjustments' => 1, 'quantity' => 6, 'audit' => 1,
    ]));
});

it('revierte las 4 migraciones de traslados con una fila transfer.approved en la bitácora y vuelve a migrar', function () {
    $transfer = transferWith([[Lot::factory()->create(), 1]], TransferStatus::Requested);
    $this->actingAs($this->regente)->postJson("/api/transfers/{$transfer->id}/approve")->assertOk();
    expect(AuditEvent::where('action', 'transfer.approved')->count())->toBe(1);

    // Las 4 de traslados y las posteriores (S5+): un conteo fijo dejaría de alcanzar `transfers`.
    $steps = DB::table('migrations')->where('migration', '>=', '2026_10_10_000001_create_transfers_table')->count();
    expect($steps)->toBeGreaterThanOrEqual(4)
        ->and(Artisan::call('migrate:rollback', ['--step' => $steps, '--force' => true]))->toBe(0);
    expect(Schema::hasTable('transfers'))->toBeFalse()
        ->and(AuditEvent::where('action', 'transfer.approved')->count())->toBe(1);

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);
    expect(Schema::hasTable('transfers'))->toBeTrue();
});
