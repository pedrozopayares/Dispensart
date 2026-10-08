<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMinimum;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Mínimos semilla (inventory-alerts «Mínimos semilla», design D7). Corre tras StockSeeder. Clave natural:
 * bodega + producto. Solo crea: un mínimo ya presente, aunque se haya cambiado a mano, no se toca.
 */
class StockMinimumSeeder extends Seeder
{
    /**
     * [código de bodega, código de producto, mínimo]. Sobre las existencias semilla: FC/MED-006 (8 < 20),
     * BH/MED-004 (60 no vencidas < 64; con el lote vencido serían 66) y BH/MED-006 (sin existencias) alertan;
     * FC/MED-001 (140 ≥ 50) tiene mínimo y no alerta. Los demás pares con existencias no tienen mínimo.
     *
     * @var list<array{0: string, 1: string, 2: int}>
     */
    public const MINIMUMS = [
        ['FC', 'MED-006', 20],
        ['BH', 'MED-004', 64],
        ['BH', 'MED-006', 5],
        ['FC', 'MED-001', 50],
    ];

    public function run(): void
    {
        foreach (self::MINIMUMS as [$warehouseCode, $productCode, $minimum]) {
            $warehouseId = Warehouse::query()->where('code', $warehouseCode)->value('id');
            $productId = Product::query()->where('code', $productCode)->value('id');

            if ($warehouseId === null || $productId === null) {
                continue;
            }

            StockMinimum::query()->firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_id' => $productId],
                ['minimum_quantity' => $minimum],
            );
        }
    }
}
