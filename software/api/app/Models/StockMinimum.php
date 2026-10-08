<?php

namespace App\Models;

use Database\Factories\StockMinimumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock mínimo de un producto en una bodega (RN-11). Sin fila, el producto no tiene mínimo en esa bodega.
 * Solo se escribe por siembra (design D7); no hay ruta de edición.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int $minimum_quantity
 */
#[Fillable(['warehouse_id', 'product_id', 'minimum_quantity'])]
class StockMinimum extends Model
{
    /** @use HasFactory<StockMinimumFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_quantity' => 'integer',
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
}
