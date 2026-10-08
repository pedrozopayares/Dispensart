<?php

namespace App\Queries;

use App\Models\KardexMovement;
use App\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecturas de inventario con su orden contractual (design D9). Sin bloqueos: solo lectura.
 */
final class InventoryQuery
{
    /**
     * Existencias con cantidad > 0, por bodega, producto, vencimiento y id de lote.
     *
     * @param  array{warehouse_id?: int, product_id?: int, lot_id?: int}  $filters
     * @return Collection<int, Stock>
     */
    public function stock(array $filters): Collection
    {
        return Stock::query()
            ->select('stocks.*')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->join('lots', 'lots.id', '=', 'stocks.lot_id')
            ->where('stocks.quantity', '>', 0)
            ->filter($filters)
            ->with(['warehouse', 'product', 'lot'])
            ->orderBy('warehouses.name')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->orderBy('lots.expires_on')
            ->orderBy('lots.id')
            ->get();
    }

    /**
     * Movimientos del más reciente al más antiguo (fecha, luego id), paginados.
     *
     * @param  array{warehouse_id?: int, product_id?: int, lot_id?: int}  $filters
     * @return LengthAwarePaginator<int, KardexMovement>
     */
    public function kardex(array $filters, int $perPage): LengthAwarePaginator
    {
        return KardexMovement::query()
            ->filter($filters)
            ->with(['warehouse', 'product', 'lot', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
