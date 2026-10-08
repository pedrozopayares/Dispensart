<?php

use App\Actions\Inventory\AdjustStock;
use App\Enums\Role;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// inventory "Consulta de existencias": GET /api/stock por HTTP real sobre las existencias semilla.

beforeEach(function () {
    $this->seed();
});

function seededStock(string $warehouseCode, string $lotCode): Stock
{
    return Stock::query()
        ->where('warehouse_id', Warehouse::where('code', $warehouseCode)->value('id'))
        ->where('lot_id', Lot::where('lot_code', $lotCode)->value('id'))
        ->sole();
}

it('devuelve a los roles con lectura las mismas existencias en el orden contractual', function (Role $role) {
    $expected = Stock::with(['warehouse', 'product', 'lot'])->where('quantity', '>', 0)->get()
        ->sortBy([
            fn (Stock $a, Stock $b) => $a->warehouse?->name <=> $b->warehouse?->name,
            fn (Stock $a, Stock $b) => $a->product?->name <=> $b->product?->name,
            fn (Stock $a, Stock $b) => $a->product_id <=> $b->product_id,
            fn (Stock $a, Stock $b) => $a->lot?->expires_on <=> $b->lot?->expires_on,
            fn (Stock $a, Stock $b) => $a->lot_id <=> $b->lot_id,
        ])->pluck('id')->all();

    $response = $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/stock');

    $response->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe($expected)
        ->and(count($expected))->toBe(14)
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'quantity', 'warehouse', 'product', 'lot'])
        ->and(array_keys($response->json('data.0.warehouse')))->toBe(['id', 'code', 'name'])
        ->and(array_keys($response->json('data.0.product')))->toBe(['id', 'code', 'name', 'is_controlled'])
        ->and(array_keys($response->json('data.0.lot')))->toBe(['id', 'lot_code', 'expires_on', 'is_expired']);
})->with([
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia],
    'regente_farmacia' => [Role::RegenteFarmacia],
    'auditor' => [Role::Auditor],
]);

it('combina los filtros de bodega y producto con Y', function () {
    $central = Warehouse::where('code', 'FC')->firstOrFail();
    $acetaminofen = Product::where('code', 'MED-001')->firstOrFail();

    $data = $this->actingAs(User::factory()->auditor()->create())
        ->getJson("/api/stock?warehouse_id={$central->id}&product_id={$acetaminofen->id}")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(3);
    foreach ($data as $row) {
        expect($row['warehouse']['id'])->toBe($central->id)
            ->and($row['product']['id'])->toBe($acetaminofen->id);
    }
});

it('muestra la existencia de un lote vencido con is_expired verdadero', function () {
    $expired = seededStock('FC', 'L-ACE-2401');

    $data = $this->actingAs(User::factory()->regente()->create())->getJson('/api/stock')->assertOk()->json('data');

    $row = collect($data)->firstWhere('id', $expired->id);
    expect($row['quantity'])->toBe(5)
        ->and($row['lot']['is_expired'])->toBeTrue();
});

it('omite una existencia que quedó en 0 tras un ajuste', function () {
    $stock = seededStock('FU', 'L-MOR-2402');
    app(AdjustStock::class)->handle(User::factory()->regente()->create(), adjustmentBody($stock, -$stock->quantity));

    $data = $this->actingAs(User::factory()->auxiliar()->create())->getJson('/api/stock')->assertOk()->json('data');

    expect(array_column($data, 'id'))->not->toContain($stock->id)
        ->and($data)->toHaveCount(13);
});

it('devuelve una lista vacía para un filtro sin resultados', function () {
    $this->actingAs(User::factory()->auditor()->create())->getJson('/api/stock?lot_id=999999')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('rechaza un filtro mal formado', function () {
    $this->actingAs(User::factory()->auditor()->create())->getJson('/api/stock?warehouse_id=abc')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['warehouse_id'], responseKey: 'errors');
});

it('rechaza con 403 a los roles sin lectura de inventario', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/stock')
        ->assertForbidden()
        ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);
})->with([
    'medico' => [Role::Medico],
    'admin' => [Role::Admin],
]);

it('responde 401 sin sesión', function () {
    $this->getJson('/api/stock')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
