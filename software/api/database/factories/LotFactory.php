<?php

namespace Database\Factories;

use App\Models\Lot;
use App\Models\Product;
use App\Support\BusinessCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'lot_code' => 'L'.fake()->unique()->numerify('######'),
            'expires_on' => BusinessCalendar::today()->addDays(180)->toDateString(),
        ];
    }

    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_on' => BusinessCalendar::today()->addDays($days)->toDateString(),
        ]);
    }
}
