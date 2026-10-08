<?php

use App\Models\Product;
use App\Models\StockMinimum;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// inventory-alerts «Integridad del stock mínimo en la base de datos»: sentencias directas, sin modelo ni API.

/**
 * @return array<string, mixed>
 */
function minimumRow(int $warehouseId, int $productId, int $quantity): array
{
    return [
        'warehouse_id' => $warehouseId,
        'product_id' => $productId,
        'minimum_quantity' => $quantity,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

test('Mínimo válido', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    DB::table('stock_minimums')->insert(minimumRow($warehouse->id, $product->id, 10));

    expect(DB::table('stock_minimums')->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('minimum_quantity'))
        ->toBe(10);
});

test('Mínimo cero o negativo rechazado por la base', function (int $quantity) {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    expectRejectedByDatabase(
        fn () => DB::table('stock_minimums')->insert(minimumRow($warehouse->id, $product->id, $quantity)),
        sqlState: '23514',
        constraint: 'stock_minimums_minimum_quantity_positive',
    );
    expect(DB::table('stock_minimums')->count())->toBe(0);
})->with(['cero' => [0], 'negativo' => [-5]]);

test('Mínimo duplicado', function () {
    $minimum = StockMinimum::factory()->create(['minimum_quantity' => 10]);

    expectRejectedByDatabase(
        fn () => DB::table('stock_minimums')->insert(minimumRow($minimum->warehouse_id, $minimum->product_id, 20)),
        sqlState: '23505',
        constraint: 'stock_minimums_warehouse_product_unique',
    );
    expect(DB::table('stock_minimums')->pluck('minimum_quantity')->all())->toBe([10]);
});

test('Bodega o producto inexistente', function (string $missing) {
    $warehouseId = $missing === 'warehouse' ? 999999 : Warehouse::factory()->create()->id;
    $productId = $missing === 'product' ? 999999 : Product::factory()->create()->id;

    expectRejectedByDatabase(
        fn () => DB::table('stock_minimums')->insert(minimumRow($warehouseId, $productId, 10)),
        sqlState: '23503',
        constraint: "stock_minimums_{$missing}_id_foreign",
    );
    expect(DB::table('stock_minimums')->count())->toBe(0);
})->with(['bodega' => ['warehouse'], 'producto' => ['product']]);

test('Borrar bodega o producto con mínimo', function (string $owner) {
    $minimum = StockMinimum::factory()->create();
    $table = $owner === 'warehouse' ? 'warehouses' : 'products';

    expectRejectedByDatabase(
        fn () => DB::table($table)->where('id', $minimum->{"{$owner}_id"})->delete(),
        sqlState: '23503',
        constraint: "stock_minimums_{$owner}_id_foreign",
    );
    expect(StockMinimum::whereKey($minimum->id)->exists())->toBeTrue()
        ->and(DB::table($table)->where('id', $minimum->{"{$owner}_id"})->exists())->toBeTrue();
})->with(['bodega' => ['warehouse'], 'producto' => ['product']]);
