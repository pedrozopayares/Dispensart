<?php

namespace App\Models;

use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Existencia de un lote en una bodega (RN-01). Solo App\Services\Inventory\StockLedger cambia su cantidad,
 * siempre con un movimiento de kardex en la misma transacción (RN-06).
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int $lot_id
 * @property int $quantity
 */
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Lot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * Clave global de bloqueo (design D2): lots.expires_on, lots.id, warehouse_id. Para una bodega + producto
     * es exactamente FEFO y luego id de lote. Todo bloqueo de existencias (StockLedger, FEFO de S3) usa este
     * orden; así dos transacciones nunca se esperan en ciclo.
     *
     * @param  Builder<Stock>  $query
     * @return Builder<Stock>
     */
    public function scopeInLockOrder(Builder $query): Builder
    {
        return $query
            ->join('lots as lock_lots', 'lock_lots.id', '=', $this->qualifyColumn('lot_id'))
            ->select($this->qualifyColumn('*'))
            ->orderBy('lock_lots.expires_on')
            ->orderBy('lock_lots.id')
            ->orderBy($this->qualifyColumn('warehouse_id'));
    }

    /**
     * Filtros opcionales combinados con Y (inventory "Consulta de existencias").
     *
     * @param  Builder<Stock>  $query
     * @param  array{warehouse_id?: int, product_id?: int, lot_id?: int}  $filters
     * @return Builder<Stock>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $column => $value) {
            $query->where($this->qualifyColumn($column), $value);
        }

        return $query;
    }
}
