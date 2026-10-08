<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// inventory-alerts «Consulta de alertas» por HTTP real, y cada escenario de las dos alertas pasado por la ruta con
// los mismos datos y afirmaciones que las pruebas de consulta (tests/Helpers/Alerts.php). Reloj fijado en 2027.

beforeEach(function () {
    freezeAlertClock();
});

test('Roles con lectura de inventario', function () {
    $this->seed();

    $bodies = [];
    foreach ([Role::AuxiliarFarmacia, Role::RegenteFarmacia, Role::Auditor] as $role) {
        $bodies[$role->value] = $this->actingAs(User::factory()->withRole($role)->create())
            ->getJson('/api/alerts')
            ->assertOk()
            ->json();
    }
    $body = $bodies['auditor'];

    expect($bodies['auxiliar_farmacia'])->toBe($body)
        ->and($bodies['regente_farmacia'])->toBe($body)
        ->and(array_keys($body))->toBe(['data'])
        ->and(array_keys($body['data']))->toBe(['expiring_lots', 'low_stock'])
        ->and($body['data']['expiring_lots'])->not->toBeEmpty()
        ->and($body['data']['low_stock'])->not->toBeEmpty();

    // Forma completa del contrato (design § API contract).
    $horizon = BusinessCalendar::today()->addDays(90)->toDateString();
    foreach ($body['data']['expiring_lots'] as $row) {
        expect(array_keys($row))->toBe(['warehouse', 'product', 'lot', 'quantity', 'days_to_expiry'])
            ->and(array_keys($row['warehouse']))->toBe(['id', 'code', 'name'])
            ->and(array_keys($row['product']))->toBe(['id', 'code', 'name', 'is_controlled'])
            ->and(array_keys($row['lot']))->toBe(['id', 'lot_code', 'expires_on', 'is_expired'])
            ->and($row['quantity'])->toBeInt()->toBeGreaterThan(0)
            ->and($row['days_to_expiry'])->toBeInt()
            ->and($row['lot']['expires_on'] <= $horizon)->toBeTrue();
    }
    foreach ($body['data']['low_stock'] as $row) {
        expect(array_keys($row))->toBe(['warehouse', 'product', 'minimum_quantity', 'available_quantity'])
            ->and(array_keys($row['warehouse']))->toBe(['id', 'code', 'name'])
            ->and(array_keys($row['product']))->toBe(['id', 'code', 'name', 'is_controlled'])
            ->and($row['minimum_quantity'])->toBeInt()
            ->and($row['available_quantity'])->toBeInt()->toBeLessThan($row['minimum_quantity']);
    }

    // Semilla (design D7): el lote vencido con existencia y los tres pares alertados, en orden de bodega y producto.
    $expired = collect($body['data']['expiring_lots'])->firstWhere('lot.lot_code', 'L-ACE-2401');
    expect($expired['lot']['is_expired'])->toBeTrue()
        ->and($expired['days_to_expiry'])->toBe(-10)
        ->and(array_map(fn (array $row): string => "{$row['warehouse']['code']}/{$row['product']['code']}", $body['data']['low_stock']))
        ->toBe(['BH/MED-004', 'BH/MED-006', 'FC/MED-006']);
});

test('Filtro por bodega', function () {
    $central = seedWarehouse('FC');
    $urgencias = seedWarehouse('FU');
    foreach ([$central, $urgencias] as $warehouse) {
        $lot = lotExpiringIn(30);
        stockAtWarehouse($warehouse, $lot, 3);
        minimumOf($warehouse, $lot->product, 10);
    }
    $auditor = User::factory()->auditor()->create();

    $all = $this->actingAs($auditor)->getJson('/api/alerts')->assertOk()->json('data');
    $filtered = $this->actingAs($auditor)->getJson("/api/alerts?warehouse_id={$urgencias->id}")->assertOk()->json('data');

    // Control: sin filtro, ambas listas traen las dos bodegas.
    foreach (['expiring_lots', 'low_stock'] as $list) {
        expect(collect($all[$list])->pluck('warehouse.id')->unique()->sort()->values()->all())
            ->toBe(collect([$central->id, $urgencias->id])->sort()->values()->all())
            ->and($filtered[$list])->toHaveCount(1)
            ->and(array_column(array_column($filtered[$list], 'warehouse'), 'id'))->each->toBe($urgencias->id);
    }
});

test('Sin alertas', function () {
    $warehouse = seedWarehouse('FC');
    $lot = lotExpiringIn(91);
    stockAtWarehouse($warehouse, $lot, 20);
    minimumOf($warehouse, $lot->product, 20);

    $this->actingAs(User::factory()->regente()->create())->getJson('/api/alerts')
        ->assertOk()
        ->assertExactJson(['data' => ['expiring_lots' => [], 'low_stock' => []]]);
});

test('Bodega inexistente', function () {
    $this->seed();
    $auditor = User::factory()->auditor()->create();

    // Control: sin filtro hay alertas en ambas listas.
    $this->actingAs($auditor)->getJson('/api/alerts')->assertOk()
        ->assertJsonPath('data.expiring_lots', fn (array $rows): bool => $rows !== [])
        ->assertJsonPath('data.low_stock', fn (array $rows): bool => $rows !== []);
    $this->actingAs($auditor)->getJson('/api/alerts?warehouse_id=999999')
        ->assertOk()
        ->assertExactJson(['data' => ['expiring_lots' => [], 'low_stock' => []]]);
});

test('Filtro mal formado', function (string $value) {
    $this->actingAs(User::factory()->auditor()->create())->getJson("/api/alerts?warehouse_id={$value}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['warehouse_id'], responseKey: 'errors');
})->with(['abc' => ['abc'], 'cero' => ['0']]);

test('Roles sin lectura de inventario', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/alerts')
        ->assertForbidden()
        ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);
})->with([
    'medico' => [Role::Medico],
    'admin' => [Role::Admin],
]);

test('Sin sesión', function () {
    $this->getJson('/api/alerts')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

// Alerta de vencimiento y Alerta de stock bajo mínimo, cada escenario por la ruta con el rol de su WHEN.
test('escenario de vencimiento por la ruta', function (string $title, Role $role) {
    $user = User::factory()->withRole($role)->create();

    expiringScenarios()[$title](fn (): array => expiringFromJson(
        $this->actingAs($user)->getJson('/api/alerts')->assertOk()->json('data.expiring_lots'),
    ));
})->with([
    'Lote que vence en 90 días incluido' => ['Lote que vence en 90 días incluido', Role::RegenteFarmacia],
    'Lote que vence en 91 días excluido' => ['Lote que vence en 91 días excluido', Role::RegenteFarmacia],
    'Lote ya vencido con existencia' => ['Lote ya vencido con existencia', Role::Auditor],
    'Lote próximo a vencer sin existencia' => ['Lote próximo a vencer sin existencia', Role::AuxiliarFarmacia],
    'Mismo lote en dos bodegas' => ['Mismo lote en dos bodegas', Role::Auditor],
    'Orden por vencimiento' => ['Orden por vencimiento', Role::RegenteFarmacia],
    'Frontera del día en hora de Bogotá' => ['Frontera del día en hora de Bogotá', Role::RegenteFarmacia],
]);

test('escenario de stock bajo mínimo por la ruta', function (string $title, Role $role) {
    $user = User::factory()->withRole($role)->create();

    lowStockScenarios()[$title](fn (): array => lowStockFromJson(
        $this->actingAs($user)->getJson('/api/alerts')->assertOk()->json('data.low_stock'),
    ));
})->with([
    'Producto bajo su mínimo' => ['Producto bajo su mínimo', Role::RegenteFarmacia],
    'Existencia igual al mínimo' => ['Existencia igual al mínimo', Role::RegenteFarmacia],
    'Suma de varios lotes cubre el mínimo' => ['Suma de varios lotes cubre el mínimo', Role::Auditor],
    'Sin existencias con mínimo definido' => ['Sin existencias con mínimo definido', Role::AuxiliarFarmacia],
    'Existencia vencida no cuenta' => ['Existencia vencida no cuenta', Role::RegenteFarmacia],
    'Mínimo propio de cada bodega' => ['Mínimo propio de cada bodega', Role::Auditor],
    'Producto sin mínimo' => ['Producto sin mínimo', Role::RegenteFarmacia],
    'Unidades en tránsito no cuentan hasta la recepción' => ['Unidades en tránsito no cuentan hasta la recepción', Role::RegenteFarmacia],
]);
