<?php

namespace App\Services\Dispensation;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

/**
 * Existencias candidatas a FEFO en una bodega (design D3): una sola sentencia para todos los productos, en
 * la clave global de bloqueo de S2 (Stock::scopeInLockOrder), así dos dispensaciones nunca se esperan en ciclo
 * sin importar el orden de los ítems. Con bloqueo, `FOR UPDATE OF stocks` (no bloquea lots); una fila que otra
 * transacción dejó en 0 se reevalúa al obtener el bloqueo y sale del resultado. La fecha no se filtra aquí.
 */
final class StockCandidates
{
    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Stock>
     */
    public function for(int $warehouseId, array $productIds, bool $lock): Collection
    {
        $query = Stock::query()
            ->inLockOrder()
            ->where('stocks.warehouse_id', $warehouseId)
            ->whereIn('stocks.product_id', $productIds)
            ->where('stocks.quantity', '>', 0)
            ->with('lot');

        if ($lock) {
            $query->lock('for update of stocks');
        }

        return $query->get();
    }
}
