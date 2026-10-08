<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMinimum;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMinimum>
 */
class StockMinimumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'minimum_quantity' => 10,
        ];
    }
}
