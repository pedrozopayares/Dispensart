<?php

namespace App\Models;

use Database\Factories\PrescriptionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ítem de una prescripción con su saldo dispensado (RN-04). Solo DispenseMedication cambia
 * dispensed_quantity, con incremento relativo bajo bloqueo; la base impide superar lo prescrito.
 *
 * @property int $id
 * @property int $prescription_id
 * @property int $product_id
 * @property int $prescribed_quantity
 * @property int $dispensed_quantity
 */
#[Fillable(['prescription_id', 'product_id', 'prescribed_quantity'])]
class PrescriptionItem extends Model
{
    /** @use HasFactory<PrescriptionItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'dispensed_quantity' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prescribed_quantity' => 'integer',
            'dispensed_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pendingQuantity(): int
    {
        return $this->prescribed_quantity - $this->dispensed_quantity;
    }
}
