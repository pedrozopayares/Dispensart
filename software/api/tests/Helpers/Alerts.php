<?php

use App\Actions\Inventory\AdjustStock;
use App\Enums\TransferStatus;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMinimum;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Database\Eloquent\Collection;

// Ayudas de las pruebas de alertas (S5). Cada escenario de «Alerta de vencimiento» y «Alerta de stock bajo mínimo»
// prepara sus datos y afirma sobre filas normalizadas que le entrega `$fetch`. Lo usan la prueba de consulta
// (AlertQuery) y la de la ruta (GET /api/alerts): mismos datos, misma afirmación.

/**
 * Reloj fijo en 2027, lejos de la fecha real: un `CURRENT_DATE` de la base nunca coincide (M16).
 */
function freezeAlertClock(): void
{
    test()->travelTo(CarbonImmutable::parse('2027-03-14 12:00:00', 'America/Bogota'));
}

/**
 * Bodega semilla por código (FC, FU, BH): la crea con su nombre semilla si la base está vacía.
 */
function seedWarehouse(string $code): Warehouse
{
    $name = collect(WarehouseSeeder::WAREHOUSES)->firstWhere('code', $code)['name'] ?? $code;

    return Warehouse::query()->firstOrCreate(['code' => $code], ['name' => $name]);
}

/**
 * Lote nuevo (de un producto dado o nuevo) que vence en `$days` días desde hoy en Bogotá.
 */
function lotExpiringIn(int $days, ?Product $product = null): Lot
{
    return Lot::factory()->expiringInDays($days)->create($product !== null ? ['product_id' => $product->id] : []);
}

/**
 * Existencia de un lote en una bodega, nacida con su movimiento `entrada` (StockFactory).
 */
function stockAtWarehouse(Warehouse $warehouse, Lot $lot, int $quantity): Stock
{
    return stockOf($quantity, ['warehouse_id' => $warehouse->id, 'lot_id' => $lot->id]);
}

function minimumOf(Warehouse $warehouse, Product $product, int $quantity): StockMinimum
{
    return StockMinimum::factory()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'minimum_quantity' => $quantity,
    ]);
}

/**
 * @return array{warehouse_id: int, lot_id: int, quantity: int, days_to_expiry: int, is_expired: bool}
 */
function expiringRow(Warehouse $warehouse, Lot $lot, int $quantity, int $days, bool $expired): array
{
    return ['warehouse_id' => $warehouse->id, 'lot_id' => $lot->id, 'quantity' => $quantity, 'days_to_expiry' => $days, 'is_expired' => $expired];
}

/**
 * @return array{warehouse_id: int, product_id: int, minimum_quantity: int, available_quantity: int}
 */
function lowStockRow(Warehouse $warehouse, Product $product, int $minimum, int $available): array
{
    return ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'minimum_quantity' => $minimum, 'available_quantity' => $available];
}

/**
 * @param  Collection<int, Stock>  $stocks
 * @return list<array<string, mixed>>
 */
function expiringFromQuery(Collection $stocks): array
{
    $today = BusinessCalendar::today();

    return $stocks->map(fn (Stock $stock): array => [
        'warehouse_id' => $stock->warehouse_id,
        'lot_id' => $stock->lot_id,
        'quantity' => $stock->quantity,
        'days_to_expiry' => $stock->getAttribute('days_to_expiry'),
        'is_expired' => $stock->lot?->isExpiredOn($today),
    ])->values()->all();
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<array<string, mixed>>
 */
function expiringFromJson(array $rows): array
{
    return array_map(fn (array $row): array => [
        'warehouse_id' => $row['warehouse']['id'],
        'lot_id' => $row['lot']['id'],
        'quantity' => $row['quantity'],
        'days_to_expiry' => $row['days_to_expiry'],
        'is_expired' => $row['lot']['is_expired'],
    ], $rows);
}

/**
 * @param  Collection<int, StockMinimum>  $minimums
 * @return list<array<string, mixed>>
 */
function lowStockFromQuery(Collection $minimums): array
{
    return $minimums->map(fn (StockMinimum $minimum): array => [
        'warehouse_id' => $minimum->warehouse_id,
        'product_id' => $minimum->product_id,
        'minimum_quantity' => $minimum->minimum_quantity,
        'available_quantity' => $minimum->getAttribute('available_quantity'),
    ])->values()->all();
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<array<string, mixed>>
 */
function lowStockFromJson(array $rows): array
{
    return array_map(fn (array $row): array => [
        'warehouse_id' => $row['warehouse']['id'],
        'product_id' => $row['product']['id'],
        'minimum_quantity' => $row['minimum_quantity'],
        'available_quantity' => $row['available_quantity'],
    ], $rows);
}

/**
 * Filas de un lote, en el orden recibido.
 *
 * @param  list<array<string, mixed>>  $rows
 * @return list<array<string, mixed>>
 */
function rowsOfLot(array $rows, Lot $lot): array
{
    return array_values(array_filter($rows, fn (array $row): bool => $row['lot_id'] === $lot->id));
}

/**
 * Filas de un par bodega + producto.
 *
 * @param  list<array<string, mixed>>  $rows
 * @return list<array<string, mixed>>
 */
function rowsOfPair(array $rows, Warehouse $warehouse, Product $product): array
{
    return array_values(array_filter(
        $rows,
        fn (array $row): bool => $row['warehouse_id'] === $warehouse->id && $row['product_id'] === $product->id,
    ));
}

/**
 * Escenarios de «Alerta de vencimiento», por título literal de la spec.
 *
 * @return array<string, Closure(Closure(): list<array<string, mixed>>): void>
 */
function expiringScenarios(): array
{
    return [
        'Lote que vence en 90 días incluido' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $lot = lotExpiringIn(90);
            stockAtWarehouse($warehouse, $lot, 3);

            expect(rowsOfLot($fetch(), $lot))->toBe([expiringRow($warehouse, $lot, 3, 90, false)]);
        },
        'Lote que vence en 91 días excluido' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $lot = lotExpiringIn(91);
            stockAtWarehouse($warehouse, $lot, 3);

            expect(rowsOfLot($fetch(), $lot))->toBe([]);
        },
        'Lote ya vencido con existencia' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $today = lotExpiringIn(0);
            $yesterday = lotExpiringIn(-1);
            stockAtWarehouse($warehouse, $today, 4);
            stockAtWarehouse($warehouse, $yesterday, 2);

            $rows = $fetch();
            expect(rowsOfLot($rows, $today))->toBe([expiringRow($warehouse, $today, 4, 0, true)])
                ->and(rowsOfLot($rows, $yesterday))->toBe([expiringRow($warehouse, $yesterday, 2, -1, true)]);
        },
        'Lote próximo a vencer sin existencia' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $emptied = lotExpiringIn(10);
            $stock = stockAtWarehouse($warehouse, $emptied, 4);
            app(AdjustStock::class)->handle(User::factory()->regente()->create(), adjustmentBody($stock, -4));
            // Control: otro lote a 10 días con existencia sí aparece.
            $kept = lotExpiringIn(10);
            stockAtWarehouse($warehouse, $kept, 2);

            $rows = $fetch();
            expect($stock->fresh()?->quantity)->toBe(0)
                ->and(rowsOfLot($rows, $emptied))->toBe([])
                ->and(rowsOfLot($rows, $kept))->toBe([expiringRow($warehouse, $kept, 2, 10, false)]);
        },
        'Mismo lote en dos bodegas' => function (Closure $fetch): void {
            $central = seedWarehouse('FC');
            $urgencias = seedWarehouse('FU');
            $lot = lotExpiringIn(30);
            stockAtWarehouse($urgencias, $lot, 7);
            stockAtWarehouse($central, $lot, 4);

            expect(rowsOfLot($fetch(), $lot))->toBe([
                expiringRow($central, $lot, 4, 30, false),
                expiringRow($urgencias, $lot, 7, 30, false),
            ]);
        },
        'Orden por vencimiento' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            // Insertados en orden 60, 5, 30: el id de lote no coincide con el orden de vencimiento (M21).
            $lots = [lotExpiringIn(60), lotExpiringIn(5), lotExpiringIn(30)];
            foreach ($lots as $lot) {
                stockAtWarehouse($warehouse, $lot, 1);
            }

            $mine = array_values(array_filter($fetch(), fn (array $row): bool => $row['warehouse_id'] === $warehouse->id));
            expect(array_column($mine, 'days_to_expiry'))->toBe([5, 30, 60]);
        },
        'Frontera del día en hora de Bogotá' => function (Closure $fetch): void {
            // 23:30 en Bogotá = 04:30 UTC del día siguiente; hoy de negocio sigue siendo el 14.
            test()->travelTo(CarbonImmutable::parse('2027-03-15 04:30:00', 'UTC'));
            $warehouse = Warehouse::factory()->create();
            $outside = lotExpiringIn(91);
            $edge = lotExpiringIn(90);
            stockAtWarehouse($warehouse, $outside, 3);
            stockAtWarehouse($warehouse, $edge, 3);

            $rows = $fetch();
            expect($outside->expires_on->toDateString())->toBe('2027-06-13')
                ->and(rowsOfLot($rows, $outside))->toBe([])
                ->and(rowsOfLot($rows, $edge))->toBe([expiringRow($warehouse, $edge, 3, 90, false)]);
        },
    ];
}

/**
 * Escenarios de «Alerta de stock bajo mínimo», por título literal de la spec. El de tránsito despacha y recibe
 * por las acciones reales de S4 (HTTP), nunca con filas armadas a mano.
 *
 * @return array<string, Closure(Closure(): list<array<string, mixed>>): void>
 */
function lowStockScenarios(): array
{
    return [
        'Producto bajo su mínimo' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $lot = lotExpiringIn(180);
            stockAtWarehouse($warehouse, $lot, 4);
            minimumOf($warehouse, $lot->product, 10);

            expect(rowsOfPair($fetch(), $warehouse, $lot->product))->toBe([lowStockRow($warehouse, $lot->product, 10, 4)]);
        },
        'Existencia igual al mínimo' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $equal = lotExpiringIn(180);
            stockAtWarehouse($warehouse, $equal, 10);
            minimumOf($warehouse, $equal->product, 10);
            // Control: una unidad menos sí alerta.
            $below = lotExpiringIn(180);
            stockAtWarehouse($warehouse, $below, 9);
            minimumOf($warehouse, $below->product, 10);

            $rows = $fetch();
            expect(rowsOfPair($rows, $warehouse, $equal->product))->toBe([])
                ->and(rowsOfPair($rows, $warehouse, $below->product))->toBe([lowStockRow($warehouse, $below->product, 10, 9)]);
        },
        'Suma de varios lotes cubre el mínimo' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $product = Product::factory()->create();
            stockAtWarehouse($warehouse, lotExpiringIn(100, $product), 6);
            stockAtWarehouse($warehouse, lotExpiringIn(200, $product), 5);
            minimumOf($warehouse, $product, 10);
            // Control: un solo lote de 6 no cubre el mismo mínimo en otra bodega.
            $other = Warehouse::factory()->create();
            stockAtWarehouse($other, lotExpiringIn(100, $product), 6);
            minimumOf($other, $product, 10);

            $rows = $fetch();
            expect(rowsOfPair($rows, $warehouse, $product))->toBe([])
                ->and(rowsOfPair($rows, $other, $product))->toBe([lowStockRow($other, $product, 10, 6)]);
        },
        'Sin existencias con mínimo definido' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $product = Product::factory()->create();
            minimumOf($warehouse, $product, 5);

            expect(Stock::query()->where('warehouse_id', $warehouse->id)->exists())->toBeFalse()
                ->and(rowsOfPair($fetch(), $warehouse, $product))->toBe([lowStockRow($warehouse, $product, 5, 0)]);
        },
        'Existencia vencida no cuenta' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $product = Product::factory()->create();
            stockAtWarehouse($warehouse, lotExpiringIn(180, $product), 4);
            stockAtWarehouse($warehouse, lotExpiringIn(0, $product), 20);
            minimumOf($warehouse, $product, 10);

            expect(rowsOfPair($fetch(), $warehouse, $product))->toBe([lowStockRow($warehouse, $product, 10, 4)]);
        },
        'Mínimo propio de cada bodega' => function (Closure $fetch): void {
            $central = seedWarehouse('FC');
            $urgencias = seedWarehouse('FU');
            $product = Product::factory()->create();
            stockAtWarehouse($central, lotExpiringIn(180, $product), 3);
            stockAtWarehouse($urgencias, lotExpiringIn(180, $product), 50);
            minimumOf($central, $product, 10);
            minimumOf($urgencias, $product, 10);

            $rows = $fetch();
            expect(rowsOfPair($rows, $central, $product))->toBe([lowStockRow($central, $product, 10, 3)])
                ->and(rowsOfPair($rows, $urgencias, $product))->toBe([]);
        },
        'Producto sin mínimo' => function (Closure $fetch): void {
            $warehouse = Warehouse::factory()->create();
            $withoutMinimum = Product::factory()->create();
            // Control: otro producto con mínimo y 0 unidades en la misma bodega sí alerta.
            $withMinimum = Product::factory()->create();
            minimumOf($warehouse, $withMinimum, 10);

            $rows = $fetch();
            expect(rowsOfPair($rows, $warehouse, $withoutMinimum))->toBe([])
                ->and(rowsOfPair($rows, $warehouse, $withMinimum))->toBe([lowStockRow($warehouse, $withMinimum, 10, 0)]);
        },
        'Unidades en tránsito no cuentan hasta la recepción' => function (Closure $fetch): void {
            $central = seedWarehouse('FC');
            $urgencias = seedWarehouse('FU');
            $product = Product::factory()->create();
            $lot = lotExpiringIn(180, $product);
            stockAtWarehouse($central, $lot, 12);
            stockAtWarehouse($urgencias, lotExpiringIn(200, $product), 6);
            minimumOf($central, $product, 10);
            minimumOf($urgencias, $product, 10);
            $auxiliar = User::factory()->auxiliar()->create();
            $transfer = transferWith([[$lot, 5]], TransferStatus::Approved, [
                'origin_warehouse_id' => $central->id,
                'destination_warehouse_id' => $urgencias->id,
            ]);

            transferAction($auxiliar, $transfer, 'dispatch')->assertOk()->assertJsonPath('data.status', 'EN_TRANSITO');
            $inTransit = $fetch();
            transferAction($auxiliar, $transfer, 'receive', receiveBody($transfer))->assertOk()->assertJsonPath('data.status', 'RECIBIDO');
            $received = $fetch();

            expect(rowsOfPair($inTransit, $central, $product))->toBe([lowStockRow($central, $product, 10, 7)])
                ->and(rowsOfPair($inTransit, $urgencias, $product))->toBe([lowStockRow($urgencias, $product, 10, 6)])
                ->and(rowsOfPair($received, $central, $product))->toBe([lowStockRow($central, $product, 10, 7)])
                ->and(rowsOfPair($received, $urgencias, $product))->toBe([])
                ->and((int) Stock::query()->where('warehouse_id', $urgencias->id)->where('product_id', $product->id)->sum('quantity'))->toBe(11);
        },
    ];
}
