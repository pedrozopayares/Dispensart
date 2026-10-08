<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de un producto con su vencimiento (RN-01). El estado vencido se calcula, nunca se guarda.
 *
 * @property int $id
 * @property int $product_id
 * @property string $lot_code
 * @property CarbonImmutable $expires_on
 */
#[Fillable(['product_id', 'lot_code', 'expires_on'])]
class Lot extends Model
{
    /** @use HasFactory<LotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Vencido si expires_on es menor o igual a la fecha de negocio dada (catalog "Lote que vence hoy").
     * Se comparan fechas calendario, no instantes: expires_on no tiene zona horaria.
     */
    public function isExpiredOn(CarbonImmutable $today): bool
    {
        return $this->expires_on->toDateString() <= $today->toDateString();
    }
}
