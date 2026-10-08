<?php

use App\Actions\Inventory\AdjustStock;
use App\Enums\Role;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// kardex "Consulta del kardex" y "Sin ruta de edición ni borrado": GET /api/kardex por HTTP real.

beforeEach(function () {
    $this->seed();
});

function seededStockOf(string $warehouseCode, string $lotCode): Stock
{
    return Stock::query()
        ->where('warehouse_id', Warehouse::where('code', $warehouseCode)->value('id'))
        ->where('lot_id', Lot::where('lot_code', $lotCode)->value('id'))
        ->sole();
}

it('devuelve a los roles con lectura los movimientos del más reciente al más antiguo, paginados', function (Role $role) {
    $stock = seededStockOf('FC', 'L-AMX-2401');
    app(AdjustStock::class)->handle(User::where('email', 'regente@dispensart.test')->firstOrFail(), adjustmentBody($stock, -1));

    $response = $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/kardex');

    $response->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('data.0.type', 'ajuste');
    $ids = array_column($response->json('data'), 'id');
    $sorted = $ids;
    rsort($sorted);
    expect($ids)->toBe($sorted)
        ->and($ids)->toHaveCount(15)
        ->and(array_keys($response->json('data.0')))->toBe([
            'id', 'type', 'quantity', 'balance_after', 'reason', 'created_at', 'warehouse', 'product', 'lot', 'user',
        ])
        ->and($response->json('data.0.created_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
})->with([
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia],
    'regente_farmacia' => [Role::RegenteFarmacia],
    'auditor' => [Role::Auditor],
]);

it('ordena por fecha antes que por id', function () {
    $stock = seededStockOf('FC', 'L-AMX-2401');
    $regente = User::where('email', 'regente@dispensart.test')->firstOrFail();
    $adjustment = app(AdjustStock::class)->handle($regente, adjustmentBody($stock, -1));
    // Dentro de la transacción de la prueba now() de la base es igual para todo: una fila nueva, de id
    // mayor, con fecha explícita de ayer (inserción directa; el kardex no admite UPDATE) fija el orden por fecha.
    $later = Stock::factory()->create(['quantity' => 0]);
    KardexMovement::query()->insert([
        'warehouse_id' => $later->warehouse_id, 'product_id' => $later->product_id, 'lot_id' => $later->lot_id,
        'type' => 'entrada', 'quantity' => 1, 'balance_after' => 1, 'created_at' => now()->subDay(),
    ]);
    $older = KardexMovement::query()->latest('id')->firstOrFail();

    $ids = array_column($this->actingAs($regente)->getJson('/api/kardex')->assertOk()->json('data'), 'id');

    expect($older->id)->toBeGreaterThan($adjustment->id)
        ->and(end($ids))->toBe($older->id);
});

it('filtra por bodega, producto y lote combinados', function () {
    $target = seededStockOf('FC', 'L-ACE-2402');
    $other = seededStockOf('FU', 'L-ACE-2402');
    $regente = User::where('email', 'regente@dispensart.test')->firstOrFail();
    app(AdjustStock::class)->handle($regente, adjustmentBody($target, -1));
    app(AdjustStock::class)->handle($regente, adjustmentBody($other, -1));

    $data = $this->actingAs(User::factory()->auditor()->create())
        ->getJson("/api/kardex?warehouse_id={$target->warehouse_id}&product_id={$target->product_id}&lot_id={$target->lot_id}")
        ->assertOk()
        ->json('data');

    expect(array_column($data, 'type'))->toBe(['ajuste', 'entrada']);
    foreach ($data as $row) {
        expect([$row['warehouse']['id'], $row['product']['id'], $row['lot']['id']])
            ->toBe([$target->warehouse_id, $target->product_id, $target->lot_id]);
    }
});

it('muestra en el ajuste el nombre del regente y su motivo', function () {
    $stock = seededStockOf('BH', 'L-OMP-2401');
    $regente = User::where('email', 'regente@dispensart.test')->firstOrFail();
    app(AdjustStock::class)->handle($regente, adjustmentBody($stock, -2, 'Rotura en estantería'));

    $response = $this->actingAs(User::factory()->auditor()->create())->getJson("/api/kardex?lot_id={$stock->lot_id}");

    $response->assertOk()
        ->assertJsonPath('data.0.type', 'ajuste')
        ->assertJsonPath('data.0.user', ['id' => $regente->id, 'name' => $regente->name])
        ->assertJsonPath('data.0.reason', 'Rotura en estantería');
});

it('muestra un único movimiento entrada sin usuario para una existencia semilla', function () {
    $stock = seededStockOf('BH', 'L-LOS-2403');

    $data = $this->actingAs(User::factory()->auxiliar()->create())
        ->getJson("/api/kardex?warehouse_id={$stock->warehouse_id}&lot_id={$stock->lot_id}")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['type'])->toBe('entrada')
        ->and($data[0]['quantity'])->toBe($stock->quantity)
        ->and($data[0]['user'])->toBeNull();
});

it('devuelve data vacío para una página fuera de rango o un filtro sin resultados', function (string $query) {
    $this->actingAs(User::factory()->auditor()->create())->getJson("/api/kardex?{$query}")
        ->assertOk()
        ->assertJsonPath('data', []);
})->with(['página 999' => ['page=999'], 'lote inexistente' => ['lot_id=999999']]);

it('rechaza parámetros mal formados', function (string $query, string $field) {
    $this->actingAs(User::factory()->auditor()->create())->getJson("/api/kardex?{$query}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field], responseKey: 'errors');
})->with([
    'producto no entero' => ['product_id=abc', 'product_id'],
    'per_page 101' => ['per_page=101', 'per_page'],
]);

it('rechaza con 403 a los roles sin lectura de inventario', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/kardex')
        ->assertForbidden()
        ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);
})->with([
    'medico' => [Role::Medico],
    'admin' => [Role::Admin],
]);

it('responde 401 sin sesión', function () {
    $this->getJson('/api/kardex')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('no tiene ruta para editar ni borrar un movimiento', function (string $method) {
    $movement = KardexMovement::query()->firstOrFail();
    $before = $movement->toArray();

    $this->actingAs(User::where('email', 'regente@dispensart.test')->firstOrFail())
        ->json($method, "/api/kardex/{$movement->id}", ['quantity' => 1, 'reason' => 'Reescrito'])
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    expect($movement->fresh()?->toArray())->toBe($before);
})->with(['PATCH', 'DELETE']);
