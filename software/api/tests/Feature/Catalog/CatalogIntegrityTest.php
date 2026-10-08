<?php

use App\Models\Lot;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// catalog "Integridad del catálogo en la base de datos": inserciones y borrados directos, sin la API.

function lotRow(int $productId, string $lotCode, ?string $expiresOn = '2030-01-31'): array
{
    return array_filter([
        'product_id' => $productId,
        'lot_code' => $lotCode,
        'expires_on' => $expiresOn,
        'created_at' => now(),
        'updated_at' => now(),
    ], fn ($value) => $value !== null);
}

it('rechaza una bodega con código existente', function () {
    Warehouse::factory()->create(['code' => 'FC', 'name' => 'Farmacia Central']);

    expectRejectedByDatabase(
        fn () => DB::table('warehouses')->insert(['code' => 'FC', 'name' => 'Otra', 'created_at' => now()]),
        sqlState: '23505',
        constraint: 'warehouses_code_unique',
    );
    expect(Warehouse::count())->toBe(1);
});

it('rechaza una bodega con nombre existente', function () {
    Warehouse::factory()->create(['code' => 'FC', 'name' => 'Farmacia Central']);

    expectRejectedByDatabase(
        fn () => DB::table('warehouses')->insert(['code' => 'FX', 'name' => 'Farmacia Central', 'created_at' => now()]),
        sqlState: '23505',
        constraint: 'warehouses_name_unique',
    );
    expect(Warehouse::count())->toBe(1);
});

it('rechaza un producto con código existente', function () {
    Product::factory()->create(['code' => 'MED-001']);

    expectRejectedByDatabase(
        fn () => DB::table('products')->insert(['code' => 'MED-001', 'name' => 'Otro', 'created_at' => now()]),
        sqlState: '23505',
        constraint: 'products_code_unique',
    );
    expect(Product::count())->toBe(1);
});

it('marca is_controlled como falso por defecto en la base', function () {
    DB::table('products')->insert(['code' => 'MED-002', 'name' => 'Sin marca']);

    expect(DB::table('products')->where('code', 'MED-002')->value('is_controlled'))->toBeFalse()
        ->and(DB::table('products')->where('code', 'MED-002')->value('presentation'))->toBeNull();
});

it('rechaza un lote duplicado para el mismo producto', function () {
    $product = Product::factory()->create();
    DB::table('lots')->insert(lotRow($product->id, 'L-100'));

    expectRejectedByDatabase(
        fn () => DB::table('lots')->insert(lotRow($product->id, 'L-100', '2031-06-30')),
        sqlState: '23505',
        constraint: 'lots_product_id_lot_code_unique',
    );
    expect(Lot::count())->toBe(1);
});

it('acepta el mismo código de lote en otro producto', function () {
    [$first, $second] = Product::factory()->count(2)->create();
    DB::table('lots')->insert(lotRow($first->id, 'L-100'));

    DB::table('lots')->insert(lotRow($second->id, 'L-100'));

    expect(Lot::where('lot_code', 'L-100')->count())->toBe(2);
});

it('rechaza un lote huérfano', function () {
    expectRejectedByDatabase(
        fn () => DB::table('lots')->insert(lotRow(999999, 'L-1')),
        sqlState: '23503',
        constraint: 'lots_product_id_foreign',
    );
    expect(Lot::count())->toBe(0);
});

it('rechaza un lote sin vencimiento', function () {
    $product = Product::factory()->create();

    expectRejectedByDatabase(
        fn () => DB::table('lots')->insert(lotRow($product->id, 'L-1', null)),
        sqlState: '23502',
    );
    expect(Lot::count())->toBe(0);
});

it('rechaza borrar un producto con lotes y conserva ambos', function () {
    $lot = Lot::factory()->create();

    expectRejectedByDatabase(
        fn () => DB::table('products')->where('id', $lot->product_id)->delete(),
        sqlState: '23503',
        constraint: 'lots_product_id_foreign',
    );
    expect(Product::whereKey($lot->product_id)->exists())->toBeTrue()
        ->and(Lot::whereKey($lot->id)->exists())->toBeTrue();
});
