<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto del catálogo; is_controlled marca los de control especial (RN-05).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $presentation
 * @property bool $is_controlled
 */
#[Fillable(['code', 'name', 'presentation', 'is_controlled'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * El valor por defecto de la base, reflejado en el modelo recién creado.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_controlled' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_controlled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Lot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
