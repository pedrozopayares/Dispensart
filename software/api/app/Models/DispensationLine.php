<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote consumido por una dispensación, enlazado a su movimiento salida_dispensacion (RN-06, design D2).
 *
 * @property int $id
 * @property int $dispensation_id
 * @property int $prescription_id
 * @property int $prescription_item_id
 * @property int $product_id
 * @property int $lot_id
 * @property int $quantity
 * @property int $kardex_movement_id
 */
class DispensationLine extends Model
{
    public $timestamps = false;

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
     * @return BelongsTo<Lot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * @return BelongsTo<KardexMovement, $this>
     */
    public function kardexMovement(): BelongsTo
    {
        return $this->belongsTo(KardexMovement::class);
    }
}
