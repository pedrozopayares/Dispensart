<?php

namespace App\Models;

use Database\Factories\TransferLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de traslado: lote + cantidad, producto derivado del lote. Inmutable tras crear salvo lo que fijan el
 * despacho (movimiento) y la recepción (cantidad recibida y movimiento).
 *
 * @property int $id
 * @property int $transfer_id
 * @property int $lot_id
 * @property int $product_id
 * @property int $quantity
 * @property int|null $received_quantity
 * @property int|null $dispatch_movement_id
 * @property int|null $receipt_movement_id
 */
class TransferLine extends Model
{
    /** @use HasFactory<TransferLineFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return BelongsTo<Lot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
