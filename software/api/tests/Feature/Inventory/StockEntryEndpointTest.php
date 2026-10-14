<?php

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// inventory "Ingreso de lote" y audit-trail "Ingresos de lote registrados" (S18): POST /api/stock-entries por
// HTTP real. Fecha de negocio fija: hoy es 2027-03-14 en America/Bogota.

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 23:30:00', 'America/Bogota'));
    $this->regente = User::factory()->regente()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
});

/**
 * Cuerpo válido de POST /api/stock-entries para la bodega y el producto de la prueba.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function entryBody(array $overrides = []): array
{
    return [
        'warehouse_id' => test()->warehouse->id,
        'product_id' => test()->product->id,
        'lot_code' => 'L-2027-NEW',
        'expires_on' => '2028-03-14',
        'quantity' => 50,
        'reason' => 'Factura FAC-9911 proveedor',
        ...$overrides,
    ];
}

/**
 * Afirma que el ingreso rechazado no dejó lote nuevo, existencia, movimiento ni fila de bitácora.
 */
function expectNoIntake(int $lots, int $movements): void
{
    expect(Lot::count())->toBe($lots)
        ->and(KardexMovement::count())->toBe($movements)
        ->and(AuditEvent::where('action', 'stock.received')->count())->toBe(0);
}

describe('ingreso exitoso', function () {
    it('Ingreso de lote nuevo: crea el lote, la existencia y un movimiento entrada', function () {
        $response = $this->actingAs($this->regente)->postJson('/api/stock-entries', entryBody());

        $response->assertCreated()
            ->assertJsonPath('data.type', 'entrada')
            ->assertJsonPath('data.quantity', 50)
            ->assertJsonPath('data.balance_after', 50)
            ->assertJsonPath('data.reason', 'Factura FAC-9911 proveedor')
            ->assertJsonPath('data.user.id', $this->regente->id)
            ->assertJsonPath('data.warehouse.id', $this->warehouse->id)
            ->assertJsonPath('data.product.id', $this->product->id)
            ->assertJsonPath('data.lot.lot_code', 'L-2027-NEW');
        $lot = Lot::where('product_id', $this->product->id)->where('lot_code', 'L-2027-NEW')->sole();
        expect($lot->expires_on->toDateString())->toBe('2028-03-14')
            ->and($response->json('data.lot.id'))->toBe($lot->id)
            ->and(Stock::where('warehouse_id', $this->warehouse->id)->where('lot_id', $lot->id)->sole()->quantity)->toBe(50);
    });

    it('Ingreso sobre lote existente con el mismo vencimiento: reutiliza el lote y suma a la existencia', function () {
        $lot = Lot::factory()->create(['product_id' => $this->product->id, 'lot_code' => 'L-OLD', 'expires_on' => '2027-12-31']);
        $stock = stockOf(10, ['warehouse_id' => $this->warehouse->id, 'lot_id' => $lot->id]);

        $this->actingAs($this->regente)
            ->postJson('/api/stock-entries', entryBody(['lot_code' => 'L-OLD', 'expires_on' => '2027-12-31', 'quantity' => 5]))
            ->assertCreated()
            ->assertJsonPath('data.lot.id', $lot->id)
            ->assertJsonPath('data.balance_after', 15);

        expect(Lot::count())->toBe(1)
            ->and($stock->fresh()?->quantity)->toBe(15)
            ->and(movementsOf($stock)->pluck('type.value')->all())->toBe(['entrada', 'entrada']);
    });

    it('acepta un lote que vence mañana', function () {
        $this->actingAs($this->regente)
            ->postJson('/api/stock-entries', entryBody(['expires_on' => '2027-03-15']))
            ->assertCreated();
    });

    it('ignora usuario, saldo, fecha y tipo enviados por el cliente', function () {
        $other = User::factory()->regente()->create();

        $this->actingAs($this->regente)->postJson('/api/stock-entries', entryBody([
            'user_id' => $other->id, 'balance_after' => 999, 'created_at' => '2000-01-01T00:00:00Z', 'type' => 'ajuste',
        ]))->assertCreated()
            ->assertJsonPath('data.type', 'entrada')
            ->assertJsonPath('data.user.id', $this->regente->id)
            ->assertJsonPath('data.balance_after', 50);
    });
});

describe('ingreso rechazado', function () {
    it('Vencimiento distinto del lote existente: 422 en expires_on sin cambiar nada', function () {
        $lot = Lot::factory()->create(['product_id' => $this->product->id, 'lot_code' => 'L-OLD', 'expires_on' => '2027-12-31']);
        stockOf(10, ['warehouse_id' => $this->warehouse->id, 'lot_id' => $lot->id]);

        $this->actingAs($this->regente)
            ->postJson('/api/stock-entries', entryBody(['lot_code' => 'L-OLD', 'expires_on' => '2028-01-31']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.expires_on.0', __('validation.lot_expiry_mismatch'));

        expect($lot->fresh()?->expires_on->toDateString())->toBe('2027-12-31');
        expectNoIntake(lots: 1, movements: 1);
    });

    it('Lote vencido rechazado: vence hoy en America/Bogota, 422 lot_expired', function () {
        $this->actingAs($this->regente)
            ->postJson('/api/stock-entries', entryBody(['expires_on' => '2027-03-14']))
            ->assertStatus(422)
            ->assertExactJson(['code' => 'lot_expired', 'message' => __('errors.lot_expired')]);

        expectNoIntake(lots: 0, movements: 0);
    });

    it('Lote vencido rechazado también sobre un lote existente con su mismo vencimiento', function () {
        Lot::factory()->create(['product_id' => $this->product->id, 'lot_code' => 'L-EXP', 'expires_on' => '2027-01-31']);

        $this->actingAs($this->regente)
            ->postJson('/api/stock-entries', entryBody(['lot_code' => 'L-EXP', 'expires_on' => '2027-01-31']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'lot_expired');

        expectNoIntake(lots: 1, movements: 0);
    });

    it('Datos inválidos o incompletos: 422 validation_failed en el campo afectado', function (array $overrides, string $field) {
        $this->actingAs($this->regente)
            ->postJson('/api/stock-entries', entryBody($overrides))
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([$field], 'errors');

        expectNoIntake(lots: 0, movements: 0);
    })->with([
        'sin motivo' => [['reason' => null], 'reason'],
        'motivo de más de 500' => [['reason' => str_repeat('a', 501)], 'reason'],
        'código de solo espacios' => [['lot_code' => '   '], 'lot_code'],
        'código de más de 50' => [['lot_code' => str_repeat('A', 51)], 'lot_code'],
        'cantidad 0' => [['quantity' => 0], 'quantity'],
        'cantidad negativa' => [['quantity' => -1], 'quantity'],
        'cantidad 1000001' => [['quantity' => 1_000_001], 'quantity'],
        'cantidad decimal' => [['quantity' => 2.5], 'quantity'],
        'vencimiento que no es fecha' => [['expires_on' => '14/03/2028'], 'expires_on'],
        'producto inexistente' => [['product_id' => 999999], 'product_id'],
        'bodega inexistente' => [['warehouse_id' => 999999], 'warehouse_id'],
    ]);

    it('Otro rol intenta registrar un ingreso: 403 forbidden', function (Role $role) {
        $this->actingAs(User::factory()->withRole($role)->create())
            ->postJson('/api/stock-entries', entryBody())
            ->assertStatus(403)
            ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);

        expectNoIntake(lots: 0, movements: 0);
    })->with([
        'auxiliar_farmacia' => [Role::AuxiliarFarmacia],
        'medico' => [Role::Medico],
        'auditor' => [Role::Auditor],
        'admin' => [Role::Admin],
    ]);

    it('Sin sesión: 401 unauthenticated', function () {
        $this->postJson('/api/stock-entries', entryBody())
            ->assertStatus(401)
            ->assertJsonPath('code', 'unauthenticated');

        expectNoIntake(lots: 0, movements: 0);
    });
});

describe('bitácora', function () {
    it('Ingreso registrado: una fila stock.received con el regente como actor y el movimiento entrada como objeto', function () {
        $response = $this->actingAs($this->regente)->postJson('/api/stock-entries', entryBody())->assertCreated();

        $row = AuditEvent::where('action', 'stock.received')->sole();
        expect($row->actor_id)->toBe($this->regente->id)
            ->and($row->subject_type)->toBe('kardex_movement')
            ->and($row->subject_id)->toBe($response->json('data.id'))
            ->and($row->details)->toEqual(['warehouse_id' => $this->warehouse->id, 'lot_id' => $response->json('data.lot.id')]);
    });

    it('Sin texto libre en la fila: ni el motivo ni el código de lote', function () {
        $this->actingAs($this->regente)->postJson('/api/stock-entries', entryBody())->assertCreated();

        $raw = (string) json_encode(DB::table('audit_events')->where('action', 'stock.received')->sole());
        expect($raw)->not->toContain('FAC-9911')
            ->and($raw)->not->toContain('L-2027-NEW')
            ->and($raw)->not->toContain('proveedor');
    });

    it('Acción con objeto equivocado rechazada por la base: stock.received sobre user', function () {
        expectRejectedByDatabase(fn () => DB::table('audit_events')->insert([
            'actor_id' => $this->regente->id, 'action' => 'stock.received', 'subject_type' => 'user',
            'subject_id' => $this->regente->id, 'details' => '{}',
        ]), '23514', 'audit_events_action_subject_check');
    });
});
