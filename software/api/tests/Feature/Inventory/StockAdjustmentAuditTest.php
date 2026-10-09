<?php

use App\Enums\TransferStatus;
use App\Models\AuditEvent;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\SpaClient;

// audit-trail "Bitácora de ajustes de inventario" (S10): POST /api/stock-adjustments por HTTP real, más la
// frontera con la resolución de discrepancias y la dispensación. Las carreras están en StockAdjustmentRaceTest.
// DatabaseMigrations y no RefreshDatabase: el fallo forzado debe observar lo que de verdad queda confirmado.
// Dentro de la transacción de RefreshDatabase, una fila escrita fuera de la transacción del ajuste abortaría
// la transacción de la prueba y ninguna aserción posterior podría leer el estado.

uses(DatabaseMigrations::class);

const SENSITIVE_REASON = 'Rotura frente a Ana Sintética 9999010001';

beforeEach(function () {
    $this->regente = User::factory()->regente()->create();
});

afterEach(function () {
    // El disparador de fallo forzado cae con la tabla en el rollback; la función se borra aquí.
    DB::unprepared('DROP FUNCTION IF EXISTS test_fail_stock_adjusted() CASCADE');
});

function adjustedRows(): int
{
    return AuditEvent::query()->where('action', 'stock.adjusted')->count();
}

/**
 * Movimientos `ajuste` de una existencia, en orden de inserción.
 *
 * @return list<int>
 */
function adjustmentIdsOf(Stock $stock): array
{
    return movementsOf($stock)->filter(fn (KardexMovement $m): bool => $m->type->value === 'ajuste')->pluck('id')->values()->all();
}

test('Ajuste registrado: una fila stock.adjusted con el regente como actor y el movimiento como objeto', function () {
    $stock = stockOf(10);

    $response = $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -3))->assertCreated();

    $row = AuditEvent::sole();
    $movementId = $response->json('data.id');
    // El objeto es el movimiento, no el lote ni la bodega: los tres ids difieren aquí.
    expect($movementId)->not->toBeIn([$stock->lot_id, $stock->warehouse_id])
        ->and(adjustmentIdsOf($stock))->toBe([$movementId])
        ->and([$row->action->value, $row->actor_id, $row->subject_type, $row->subject_id, $row->details])
        ->toBe(['stock.adjusted', $this->regente->id, 'kardex_movement', $movementId, ['lot_id' => $stock->lot_id, 'warehouse_id' => $stock->warehouse_id]])
        ->and($row->correlation_id)->toBe($response->headers->get('X-Correlation-Id'))->not->toBeNull()
        ->and($row->created_at)->not->toBeNull();
});

test('Sin texto libre en la fila: el motivo y sus palabras distintivas no aparecen', function () {
    $stock = stockOf(10);

    $id = $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -3, SENSITIVE_REASON))
        ->assertCreated()->json('data.id');

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $row = (string) json_encode(AuditEvent::sole()->getAttributes(), $flags);
    $movement = (string) json_encode(KardexMovement::findOrFail($id)->getAttributes(), $flags);
    foreach ([SENSITIVE_REASON, 'Ana', 'Sintética', '9999010001'] as $value) {
        // Control positivo: la misma búsqueda encuentra el valor en el movimiento del kardex.
        expect($movement)->toContain($value)
            ->and($row)->not->toContain($value);
    }
});

test('Fallo al registrar revierte el ajuste: 500 server_error, existencia en 10 y sin movimiento nuevo', function () {
    DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION test_fail_stock_adjusted() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN RAISE EXCEPTION 'fallo forzado de la bitácora'; END; $$;
        CREATE TRIGGER test_fail_stock_adjusted BEFORE INSERT ON audit_events
            FOR EACH ROW WHEN (NEW.action = 'stock.adjusted') EXECUTE FUNCTION test_fail_stock_adjusted();
        SQL);
    $stock = stockOf(10);

    $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -3))
        ->assertStatus(500)
        ->assertExactJson(['code' => 'server_error', 'message' => __('errors.server_error')]);

    expect($stock->fresh()?->quantity)->toBe(10)
        ->and(adjustmentIdsOf($stock))->toBe([])
        ->and(KardexMovement::count())->toBe(1)
        ->and(AuditEvent::count())->toBe(0);
});

test('Ajuste rechazado sin fila: 401, 409, 422 lot_expired, 422 validation_failed, 403 y 419', function () {
    $stock = stockOf(10);
    $expired = stockOf(5, ['lot_id' => Lot::factory()->create(['expires_on' => BusinessCalendar::today()->subDay()->toDateString()])->id]);
    $auditor = User::factory()->auditor()->create();

    // Sin sesión primero: actingAs deja la sesión puesta para las peticiones siguientes.
    $this->postJson('/api/stock-adjustments', adjustmentBody($stock, -1))->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    expect(AuditEvent::count())->toBe(0);

    $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -11))
        ->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');
    expect(AuditEvent::count())->toBe(0);

    $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($expired, 2))
        ->assertStatus(422)->assertJsonPath('code', 'lot_expired');
    expect(AuditEvent::count())->toBe(0);

    $this->actingAs($this->regente)->postJson('/api/stock-adjustments', array_diff_key(adjustmentBody($stock, -1), ['reason' => true]))
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    expect(AuditEvent::count())->toBe(0);

    $this->actingAs($auditor)->postJson('/api/stock-adjustments', adjustmentBody($stock, -1))
        ->assertForbidden()->assertJsonPath('code', 'forbidden');
    expect(AuditEvent::count())->toBe(0);

    $spa = new SpaClient($this);
    $spa->loginAs($this->regente);
    $spa->post('/api/stock-adjustments', adjustmentBody($stock, -1), withXsrf: false)
        ->assertStatus(419)->assertJsonPath('code', 'csrf_token_mismatch');
    expect(AuditEvent::count())->toBe(0)
        ->and($stock->fresh()?->quantity)->toBe(10)
        ->and($expired->fresh()?->quantity)->toBe(5);
});

test('Reintento del mismo ajuste con dos filas: una por cada movimiento ajuste', function () {
    $stock = stockOf(5);

    foreach ([1, 2] as $attempt) {
        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -1))->assertCreated();
    }

    $adjustments = adjustmentIdsOf($stock);
    expect($adjustments)->toHaveCount(2)
        ->and(AuditEvent::query()->where('action', 'stock.adjusted')->orderBy('id')->pluck('subject_id')->all())->toBe($adjustments);
});

test('Resolución de discrepancia sin fila de ajuste: solo transfer.discrepancy_resolved', function () {
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 3]], TransferStatus::Approved);
    originStock($transfer, $lot, 3);
    $auxiliar = User::factory()->auxiliar()->create();
    transferAction($auxiliar, $transfer, 'dispatch')->assertOk();
    transferAction($auxiliar, $transfer, 'receive', receiveBody($transfer, [2]))->assertOk();
    $discrepancy = TransferDiscrepancy::where('transfer_id', $transfer->id)->sole();
    $before = AuditEvent::count();

    $this->actingAs($this->regente)
        ->postJson("/api/transfers/{$transfer->id}/discrepancies/{$discrepancy->id}/resolve", [
            'resolution' => 'returned_to_origin', 'reason' => 'Unidad no cargada en el despacho',
        ])->assertOk();

    // Control positivo: la resolución sí creó un movimiento `ajuste` en origen.
    expect(KardexMovement::query()->where('type', 'ajuste')->count())->toBe(1)
        ->and(AuditEvent::count())->toBe($before + 1)
        ->and(AuditEvent::query()->where('action', 'transfer.discrepancy_resolved')->count())->toBe(1)
        ->and(adjustedRows())->toBe(0);
});

test('Dispensación y su repetición idempotente sin fila de ajuste', function () {
    $auxiliar = User::factory()->auxiliar()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    lotStock($warehouse, $product, 30, 20);
    $prescription = prescriptionWith([[$product, 10]]);
    $body = dispensationBody($prescription, $warehouse, [[$product, 3]]);
    $key = newIdempotencyKey();

    dispense($auxiliar, $body, $key)->assertCreated();
    $afterFirst = AuditEvent::count();
    dispense($auxiliar, $body, $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    // Control positivo: la dispensación sí escribió su propia fila en la bitácora.
    expect(AuditEvent::query()->where('action', 'dispensation.created')->count())->toBe(1)
        ->and(AuditEvent::count())->toBe($afterFirst)
        ->and(adjustedRows())->toBe(0);
});
