<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Prescripciones sintéticas. Estados: vigente (por defecto, vence en 30 días), vencida (venció ayer) y
 * agotada (todo ítem con pendiente 0). Sin ítems por defecto: se agregan con withItem().
 *
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'prescriber_id' => User::factory()->withRole(Role::Medico),
            'valid_until' => BusinessCalendar::today()->addDays(30)->toDateString(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'valid_until' => BusinessCalendar::today()->subDay()->toDateString(),
        ]);
    }

    /**
     * Un ítem del producto dado (o uno nuevo) con su prescrita y dispensada.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withItem(int $prescribed = 10, int $dispensed = 0, array $attributes = []): static
    {
        return $this->has(
            PrescriptionItem::factory()->state([
                'prescribed_quantity' => $prescribed, 'dispensed_quantity' => $dispensed, ...$attributes,
            ]),
            'items',
        );
    }

    /**
     * Agotada: un ítem totalmente dispensado.
     */
    public function exhausted(int $prescribed = 10): static
    {
        return $this->withItem($prescribed, $prescribed);
    }
}
