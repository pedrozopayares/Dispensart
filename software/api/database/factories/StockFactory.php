<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Existencias sintéticas coherentes con la cadena de saldos: una existencia con cantidad > 0 nace con su
 * movimiento `entrada` (RN-06), como en la siembra. El producto se toma del lote.
 *
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'lot_id' => Lot::factory(),
            'product_id' => fn (array $attributes): int => Lot::query()->findOrFail($attributes['lot_id'])->product_id,
            'quantity' => 10,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Stock $stock): void {
            if ($stock->quantity > 0) {
                (new KardexMovement)->forceFill([
                    'warehouse_id' => $stock->warehouse_id,
                    'product_id' => $stock->product_id,
                    'lot_id' => $stock->lot_id,
                    'type' => MovementType::Inbound,
                    'quantity' => $stock->quantity,
                    'balance_after' => $stock->quantity,
                ])->save();
            }
        });
    }
}
