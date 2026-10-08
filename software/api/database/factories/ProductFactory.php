<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'MED-'.fake()->unique()->numerify('#####'),
            'name' => 'Producto '.fake()->unique()->numerify('######'),
            'presentation' => 'Caja x 10 tabletas',
            'is_controlled' => false,
        ];
    }

    public function controlled(): static
    {
        return $this->state(fn (array $attributes): array => ['is_controlled' => true]);
    }
}
