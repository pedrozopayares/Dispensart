<?php

use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// inventory "Integridad de existencias en la base de datos": sentencias directas, sin pasar por la API.

/**
 * @return array<string, mixed>
 */
function stockRow(int $warehouseId, Lot $lot, int $quantity = 0, ?int $productId = null): array
{
    return [
        'warehouse_id' => $warehouseId,
        'product_id' => $productId ?? $lot->product_id,
        'lot_id' => $lot->id,
        'quantity' => $quantity,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

it('guarda una existencia válida con el producto de su lote y cantidad 0', function () {
    $warehouse = Warehouse::factory()->create();
    $lot = Lot::factory()->create();

    DB::table('stocks')->insert(stockRow($warehouse->id, $lot));

    expect(Stock::where('warehouse_id', $warehouse->id)->where('lot_id', $lot->id)->value('quantity'))->toBe(0);
});

it('rechaza en la base una cantidad negativa y conserva la anterior', function () {
    $stock = stockOf(3);

    expectRejectedByDatabase(
        fn () => DB::table('stocks')->where('id', $stock->id)->update(['quantity' => -1]),
        sqlState: '23514',
        constraint: 'stocks_quantity_non_negative',
    );
    expect($stock->fresh()?->quantity)->toBe(3);
});

it('rechaza una segunda existencia para la misma bodega, producto y lote', function () {
    $stock = stockOf(0);
    $lot = Lot::findOrFail($stock->lot_id);

    expectRejectedByDatabase(
        fn () => DB::table('stocks')->insert(stockRow($stock->warehouse_id, $lot, 5)),
        sqlState: '23505',
        constraint: 'stocks_warehouse_product_lot_unique',
    );
    expect(Stock::count())->toBe(1);
});

it('rechaza una existencia cuyo producto no es el de su lote', function () {
    $warehouse = Warehouse::factory()->create();
    $lot = Lot::factory()->create();
    $otherProduct = Product::factory()->create();

    expectRejectedByDatabase(
        fn () => DB::table('stocks')->insert(stockRow($warehouse->id, $lot, productId: $otherProduct->id)),
        sqlState: '23503',
        constraint: 'stocks_lot_product_foreign',
    );
    expect(Stock::count())->toBe(0);
});

it('rechaza borrar una bodega con existencias y conserva la existencia', function () {
    $stock = stockOf(0);

    expectRejectedByDatabase(
        fn () => DB::table('warehouses')->where('id', $stock->warehouse_id)->delete(),
        sqlState: '23503',
        constraint: 'stocks_warehouse_id_foreign',
    );
    expect(Stock::whereKey($stock->id)->exists())->toBeTrue();
});

it('rechaza borrar un lote con existencias y conserva la existencia', function () {
    $stock = stockOf(0);

    expectRejectedByDatabase(
        fn () => DB::table('lots')->where('id', $stock->lot_id)->delete(),
        sqlState: '23503',
        constraint: 'stocks_lot_product_foreign',
    );
    expect(Stock::whereKey($stock->id)->exists())->toBeTrue();
});
