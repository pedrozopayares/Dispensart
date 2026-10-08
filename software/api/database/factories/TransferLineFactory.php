<?php

namespace Database\Factories;

use App\Models\Lot;
use App\Models\Transfer;
use App\Models\TransferLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Línea sintética: el producto se toma del lote, como en la creación real.
 *
 * @extends Factory<TransferLine>
 */
class TransferLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'lot_id' => Lot::factory(),
            'product_id' => fn (array $attributes): int => Lot::query()->findOrFail($attributes['lot_id'])->product_id,
            'quantity' => 3,
        ];
    }
}
