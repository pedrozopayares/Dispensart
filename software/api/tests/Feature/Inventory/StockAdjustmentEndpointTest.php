<?php

use App\Enums\Role;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// inventory "Ajuste de inventario", "Ajuste nunca deja stock negativo", "Ajuste sobre lote vencido" y kardex
// "Un movimiento por cada cambio de existencia": POST /api/stock-adjustments por HTTP real.

beforeEach(function () {
    $this->regente = User::factory()->regente()->create();
});

/**
 * Afirma que nada cambió: misma cantidad y mismos movimientos de la existencia, y ningún movimiento nuevo.
 */
function expectUntouched(Stock $stock, int $quantity, int $movements, int $totalMovements): void
{
    expect($stock->fresh()?->quantity)->toBe($quantity)
        ->and(movementsOf($stock))->toHaveCount($movements)
        ->and(KardexMovement::count())->toBe($totalMovements);
}

describe('ajuste exitoso', function () {
    it('aplica un ajuste negativo y escribe exactamente un movimiento', function () {
        $stock = stockOf(10);

        $response = $this->actingAs($this->regente)
            ->postJson('/api/stock-adjustments', adjustmentBody($stock, -3, 'Rotura en estantería'));

        $response->assertCreated()
            ->assertJsonPath('data.type', 'ajuste')
            ->assertJsonPath('data.quantity', -3)
            ->assertJsonPath('data.balance_after', 7)
            ->assertJsonPath('data.reason', 'Rotura en estantería')
            ->assertJsonPath('data.user.id', $this->regente->id)
            ->assertJsonPath('data.warehouse.id', $stock->warehouse_id)
            ->assertJsonPath('data.lot.id', $stock->lot_id);
        expect($stock->fresh()?->quantity)->toBe(7)
            ->and(movementsOf($stock))->toHaveCount(2)
            ->and(movementsOf($stock)->last()?->id)->toBe($response->json('data.id'));
    });

    it('crea la existencia con un ajuste positivo cuando no existía', function () {
        $warehouse = Warehouse::factory()->create();
        $lot = Lot::factory()->create();

        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', [
            'warehouse_id' => $warehouse->id, 'lot_id' => $lot->id, 'quantity' => 4, 'reason' => 'Sobrante en conteo',
        ])->assertCreated()->assertJsonPath('data.balance_after', 4);

        $stock = Stock::where('warehouse_id', $warehouse->id)->where('lot_id', $lot->id)->sole();
        expect($stock->quantity)->toBe(4)
            ->and(movementsOf($stock)->sole()->type->value)->toBe('ajuste');
    });

    it('ignora usuario, saldo, fecha y tipo enviados por el cliente', function () {
        $stock = stockOf(10);
        $other = User::factory()->regente()->create();

        $response = $this->actingAs($this->regente)->postJson('/api/stock-adjustments', [
            ...adjustmentBody($stock, -1),
            'user_id' => $other->id, 'balance_after' => 999, 'created_at' => '2000-01-01T00:00:00Z', 'type' => 'entrada',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'ajuste')
            ->assertJsonPath('data.user.id', $this->regente->id)
            ->assertJsonPath('data.balance_after', 9);
        expect(CarbonImmutable::parse($response->json('data.created_at'))->year)->toBe((int) now()->year);
    });

    it('deja la existencia exactamente en cero', function () {
        $stock = stockOf(5);

        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -5))
            ->assertCreated()
            ->assertJsonPath('data.balance_after', 0);

        expect($stock->fresh()?->quantity)->toBe(0);
    });

    it('encadena los saldos tras +4, -2 y -1 sobre una existencia de 6', function () {
        $stock = stockOf(6);

        $balances = [];
        foreach ([4, -2, -1] as $quantity) {
            $balances[] = $this->actingAs($this->regente)
                ->postJson('/api/stock-adjustments', adjustmentBody($stock, $quantity))
                ->assertCreated()
                ->json('data.balance_after');
        }

        expect($balances)->toBe([10, 8, 7])
            ->and($stock->fresh()?->quantity)->toBe(7)
            ->and(movementsOf($stock)->sum('quantity'))->toBe(7);
    });

    it('aplica dos veces el mismo ajuste repetido', function () {
        $stock = stockOf(5);

        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -1))->assertCreated();
        }

        expect($stock->fresh()?->quantity)->toBe(3)
            ->and(movementsOf($stock)->where('type.value', 'ajuste'))->toHaveCount(2);
    });
});

describe('existencia insuficiente', function () {
    it('rechaza con 409 un ajuste mayor que la existencia, sin efecto', function () {
        $stock = stockOf(5);

        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -6))
            ->assertStatus(409)
            ->assertExactJson(['code' => 'insufficient_stock', 'message' => __('errors.insufficient_stock')]);

        expectUntouched($stock, quantity: 5, movements: 1, totalMovements: 1);
    });

    it('rechaza con 409 un ajuste negativo sin existencia previa y no la crea', function () {
        $warehouse = Warehouse::factory()->create();
        $lot = Lot::factory()->create();

        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', [
            'warehouse_id' => $warehouse->id, 'lot_id' => $lot->id, 'quantity' => -1, 'reason' => 'Conteo físico',
        ])->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');

        expect(Stock::count())->toBe(0)
            ->and(KardexMovement::count())->toBe(0);
    });
});

describe('lote vencido', function () {
    beforeEach(function () {
        $this->travelTo(CarbonImmutable::parse('2027-03-14 23:30:00', 'America/Bogota'));
    });

    it('permite dar de baja la existencia de un lote vencido', function () {
        $stock = stockOf(5, ['lot_id' => Lot::factory()->create(['expires_on' => '2027-03-01'])->id]);

        $this->actingAs($this->regente)
            ->postJson('/api/stock-adjustments', adjustmentBody($stock, -5, 'Baja por vencimiento'))
            ->assertCreated();

        expect($stock->fresh()?->quantity)->toBe(0);
    });

    it('rechaza con 422 un ingreso a un lote que vence hoy en Bogotá, sin efecto', function () {
        $stock = stockOf(5, ['lot_id' => Lot::factory()->create(['expires_on' => '2027-03-14'])->id]);

        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, 2))
            ->assertStatus(422)
            ->assertExactJson(['code' => 'lot_expired', 'message' => __('errors.lot_expired')]);

        expectUntouched($stock, quantity: 5, movements: 1, totalMovements: 1);
    });

    it('acepta un ingreso a un lote que vence mañana', function () {
        $stock = stockOf(5, ['lot_id' => Lot::factory()->create(['expires_on' => '2027-03-15'])->id]);

        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, 2))
            ->assertCreated()
            ->assertJsonPath('data.balance_after', 7);
    });
});

describe('rechazos sin efecto', function () {
    it('rechaza datos inválidos o incompletos por campo', function (Closure $mutate, string $field) {
        $stock = stockOf(5);

        $this->actingAs($this->regente)->postJson('/api/stock-adjustments', $mutate(adjustmentBody($stock, -1)))
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([$field], responseKey: 'errors');

        expectUntouched($stock, quantity: 5, movements: 1, totalMovements: 1);
    })->with([
        'sin motivo' => [fn (array $b) => array_diff_key($b, ['reason' => true]), 'reason'],
        'motivo de solo espacios' => [fn (array $b) => [...$b, 'reason' => '   '], 'reason'],
        'motivo de 501 caracteres' => [fn (array $b) => [...$b, 'reason' => str_repeat('a', 501)], 'reason'],
        'cantidad 0' => [fn (array $b) => [...$b, 'quantity' => 0], 'quantity'],
        'cantidad 2.5' => [fn (array $b) => [...$b, 'quantity' => 2.5], 'quantity'],
        'cantidad 1000001' => [fn (array $b) => [...$b, 'quantity' => 1_000_001], 'quantity'],
        'cantidad -1000001' => [fn (array $b) => [...$b, 'quantity' => -1_000_001], 'quantity'],
        'lote inexistente' => [fn (array $b) => [...$b, 'lot_id' => 999_999], 'lot_id'],
        'bodega inexistente' => [fn (array $b) => [...$b, 'warehouse_id' => 999_999], 'warehouse_id'],
        'bodega no entera' => [fn (array $b) => [...$b, 'warehouse_id' => 'abc'], 'warehouse_id'],
    ]);

    it('rechaza con 403 a los demás roles', function (Role $role) {
        $stock = stockOf(5);

        $this->actingAs(User::factory()->withRole($role)->create())
            ->postJson('/api/stock-adjustments', adjustmentBody($stock, -1))
            ->assertForbidden()
            ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);

        expectUntouched($stock, quantity: 5, movements: 1, totalMovements: 1);
    })->with([
        'auxiliar_farmacia' => [Role::AuxiliarFarmacia],
        'medico' => [Role::Medico],
        'auditor' => [Role::Auditor],
        'admin' => [Role::Admin],
    ]);

    it('responde 401 sin sesión', function () {
        $stock = stockOf(5);

        $this->postJson('/api/stock-adjustments', adjustmentBody($stock, -1))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        expectUntouched($stock, quantity: 5, movements: 1, totalMovements: 1);
    });

    it('rechaza con 419 un ajuste desde la SPA sin X-XSRF-TOKEN y lo acepta con él', function () {
        $stock = stockOf(5);
        $spa = new SpaClient($this);
        $spa->loginAs($this->regente);

        $spa->post('/api/stock-adjustments', adjustmentBody($stock, -1), withXsrf: false)
            ->assertStatus(419)
            ->assertJsonPath('code', 'csrf_token_mismatch');
        expectUntouched($stock, quantity: 5, movements: 1, totalMovements: 1);

        // Control positivo: la misma sesión con el token sí ajusta.
        $spa->post('/api/stock-adjustments', adjustmentBody($stock, -1))->assertCreated();
        expect($stock->fresh()?->quantity)->toBe(4);
    });
});
