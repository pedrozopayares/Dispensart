<?php

namespace App\Models;

use App\Enums\MovementType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento inmutable del kardex (RN-06). Solo se inserta, desde StockLedger; la base rechaza UPDATE,
 * DELETE y TRUNCATE (trigger kardex_movements_append_only). La fecha la pone la base.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int $lot_id
 * @property MovementType $type
 * @property int $quantity
 * @property int $balance_after
 * @property string|null $reason
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 */
class KardexMovement extends Model
{
    /**
     * Sin updated_at: un movimiento no cambia. created_at lo fija la base (DEFAULT now()).
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Filtros opcionales combinados con Y (kardex "Consulta del kardex").
     *
     * @param  Builder<KardexMovement>  $query
     * @param  array{warehouse_id?: int, product_id?: int, lot_id?: int}  $filters
     * @return Builder<KardexMovement>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $column => $value) {
            $query->where($this->qualifyColumn($column), $value);
        }

        return $query;
    }
}
