<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pacientes sintéticos: documento del rango ficticio 99990… y nombre marcado como sintético (RN-10).
 *
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => DocumentType::CedulaCiudadania,
            'document_number' => '99990'.fake()->unique()->numerify('#####'),
            'full_name' => 'Paciente Sintético '.fake()->unique()->numerify('######'),
            'birth_date' => '1980-05-17',
            'phone' => '300'.fake()->numerify('#######'),
        ];
    }
}
