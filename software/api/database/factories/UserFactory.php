<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Usuarios sintéticos. Rol por defecto: auditor (solo lectura); un estado por rol.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Contraseña en uso por la fábrica.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->userName()).'@dispensart.test',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Auditor,
        ];
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (array $attributes): array => ['role' => $role]);
    }

    public function auxiliar(): static
    {
        return $this->withRole(Role::AuxiliarFarmacia);
    }

    public function regente(): static
    {
        return $this->withRole(Role::RegenteFarmacia);
    }

    public function medico(): static
    {
        return $this->withRole(Role::Medico);
    }

    public function auditor(): static
    {
        return $this->withRole(Role::Auditor);
    }

    public function admin(): static
    {
        return $this->withRole(Role::Admin);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
