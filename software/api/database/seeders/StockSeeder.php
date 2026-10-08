<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\Inventory\StockChange;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\Seeder;

/**
 * Existencias iniciales (inventory "Existencias semilla"). Cada existencia nace por el libro con un único
 * movimiento `entrada` sin usuario (sistema). Idempotente por bodega + lote: si la existencia ya existe
 * (sembrada antes o creada por un ajuste) no se toca.
 */
class StockSeeder extends Seeder
{
    /**
     * [código de bodega, código de producto, código de lote, cantidad]. Incluye un lote vencido con existencia
     * (L-ACE-2401, L-LOS-2401: la regla de vencido no vive en el libro), el controlado (MED-006) con
     * existencia no vencida y un producto con 2 lotes no vencidos en la misma bodega (MED-001 en FC).
     *
     * @var list<array{0: string, 1: string, 2: string, 3: int}>
     */
    public const STOCKS = [
        ['FC', 'MED-001', 'L-ACE-2401', 5],
        ['FC', 'MED-001', 'L-ACE-2402', 40],
        ['FC', 'MED-001', 'L-ACE-2403', 100],
        ['FC', 'MED-002', 'L-AMX-2401', 30],
        ['FC', 'MED-003', 'L-IBU-2402', 50],
        ['FC', 'MED-006', 'L-MOR-2401', 8],
        ['FU', 'MED-001', 'L-ACE-2402', 20],
        ['FU', 'MED-003', 'L-IBU-2401', 25],
        ['FU', 'MED-004', 'L-LOS-2402', 15],
        ['FU', 'MED-006', 'L-MOR-2402', 5],
        ['BH', 'MED-002', 'L-AMX-2402', 40],
        ['BH', 'MED-004', 'L-LOS-2401', 6],
        ['BH', 'MED-004', 'L-LOS-2403', 60],
        ['BH', 'MED-005', 'L-OMP-2401', 30],
    ];

    public function run(StockLedger $ledger): void
    {
        foreach (self::STOCKS as [$warehouseCode, $productCode, $lotCode, $quantity]) {
            $warehouseId = Warehouse::query()->where('code', $warehouseCode)->value('id');
            $lotId = Lot::query()
                ->where('lot_code', $lotCode)
                ->whereHas('product', fn ($query) => $query->where('code', $productCode))
                ->value('id');

            if ($warehouseId === null || $lotId === null) {
                continue;
            }

            if (Stock::query()->where('warehouse_id', $warehouseId)->where('lot_id', $lotId)->exists()) {
                continue;
            }

            $ledger->apply([new StockChange((int) $warehouseId, (int) $lotId, $quantity, MovementType::Inbound)]);
        }
    }
}
