<?php

namespace App\Queries;

use App\Models\Stock;
use App\Models\StockMinimum;
use App\Support\BusinessCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

/**
 * Alertas de inventario (RN-11; design D2–D4, D6). Solo lectura, sin bloqueos: dos SELECT en READ COMMITTED.
 * "Hoy" sale de BusinessCalendar (America/Bogota) y entra a SQL como fecha enlazada, nunca CURRENT_DATE.
 */
final class AlertQuery
{
    /** Ventana de vencimiento en días: el día 90 alerta, el 91 no (RN-11). */
    public const EXPIRY_WINDOW_DAYS = 90;

    /**
     * Disponible de un par bodega + producto: suma de existencias no vencidas; sin filas, 0 (design D4).
     * Solo lee `stocks`: lo despachado y aún no recibido ya salió del origen y no entró al destino.
     */
    private const AVAILABLE = 'COALESCE(available.quantity, 0)';

    /**
     * Existencias con cantidad > 0 cuyo lote vence en la ventana, ya vencidas incluidas (sin límite inferior),
     * con `days_to_expiry` = vencimiento − hoy. Orden: vencimiento, id de lote, nombre de bodega.
     * `$days` y `$productId` son opcionales para el asistente (S7, design D6); sus valores por defecto son los de
     * la ruta de alertas, que no los pasa.
     *
     * @return Collection<int, Stock>
     */
    public function expiringLots(?int $warehouseId, int $days = self::EXPIRY_WINDOW_DAYS, ?int $productId = null): Collection
    {
        $today = BusinessCalendar::today();
        $horizon = $today->addDays($days);

        return Stock::query()
            ->select('stocks.*')
            ->selectRaw('(lots.expires_on - CAST(? AS date)) AS days_to_expiry', [$today->toDateString()])
            ->join('lots', 'lots.id', '=', 'stocks.lot_id')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->where('stocks.quantity', '>', 0)
            ->where('lots.expires_on', '<=', $horizon->toDateString())
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('stocks.warehouse_id', $warehouseId))
            ->when($productId !== null, fn (Builder $query) => $query->where('stocks.product_id', $productId))
            ->with(['warehouse', 'product', 'lot'])
            ->orderBy('lots.expires_on')
            ->orderBy('lots.id')
            ->orderBy('warehouses.name')
            ->get();
    }

    /**
     * Pares bodega + producto con mínimo cuyo disponible es estrictamente menor que el mínimo, con
     * `available_quantity`. Un par sin mínimo nunca aparece: la consulta parte de `stock_minimums`.
     * Orden: nombre de bodega, nombre e id de producto.
     *
     * @return Collection<int, StockMinimum>
     */
    public function lowStock(?int $warehouseId): Collection
    {
        $today = BusinessCalendar::today();

        // El predicado de no vencido va dentro de la subconsulta: fuera borraría los pares sin existencias.
        $available = Stock::query()
            ->toBase()
            ->select('stocks.warehouse_id', 'stocks.product_id')
            ->selectRaw('SUM(stocks.quantity) AS quantity')
            ->join('lots', 'lots.id', '=', 'stocks.lot_id')
            ->where('lots.expires_on', '>', $today->toDateString())
            ->groupBy('stocks.warehouse_id', 'stocks.product_id');

        return StockMinimum::query()
            ->select('stock_minimums.*')
            ->selectRaw('CAST('.self::AVAILABLE.' AS integer) AS available_quantity')
            ->join('warehouses', 'warehouses.id', '=', 'stock_minimums.warehouse_id')
            ->join('products', 'products.id', '=', 'stock_minimums.product_id')
            ->leftJoinSub($available, 'available', function (JoinClause $join): void {
                $join->on('available.warehouse_id', '=', 'stock_minimums.warehouse_id')
                    ->on('available.product_id', '=', 'stock_minimums.product_id');
            })
            ->whereRaw(self::AVAILABLE.' < stock_minimums.minimum_quantity')
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('stock_minimums.warehouse_id', $warehouseId))
            ->with(['warehouse', 'product'])
            ->orderBy('warehouses.name')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->get();
    }
}
