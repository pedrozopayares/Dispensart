<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class UserSeeder extends Seeder
{
    /**
     * Un usuario sintético por rol, todos en el dominio dispensart.test.
     *
     * @var list<array{email: string, name: string, role: Role}>
     */
    public const USERS = [
        ['email' => 'auxiliar@dispensart.test', 'name' => 'Auxiliar Demo', 'role' => Role::AuxiliarFarmacia],
        ['email' => 'regente@dispensart.test', 'name' => 'Regente Demo', 'role' => Role::RegenteFarmacia],
        ['email' => 'medico@dispensart.test', 'name' => 'Médico Demo', 'role' => Role::Medico],
        ['email' => 'auditor@dispensart.test', 'name' => 'Auditor Demo', 'role' => Role::Auditor],
        ['email' => 'admin@dispensart.test', 'name' => 'Administrador Demo', 'role' => Role::Admin],
    ];

    public function run(): void
    {
        $password = $this->password();

        if ($password === null) {
            // Nunca se registra valor alguno de contraseña.
            Log::warning('Usuarios semilla omitidos: SEED_USER_PASSWORD no está definida en producción.');

            return;
        }

        foreach (self::USERS as $seed) {
            // Clave natural: correo. Si existe, no se toca (ni contraseña ni rol).
            $user = User::query()->firstOrNew(['email' => $seed['email']]);
            if ($user->exists) {
                continue;
            }

            $user->forceFill(['name' => $seed['name'], 'password' => $password, 'role' => $seed['role']])->save();
        }
    }

    /**
     * SEED_USER_PASSWORD si está definida; si no, el valor por defecto de desarrollo, nunca en producción
     * (design D10).
     */
    private function password(): ?string
    {
        $explicit = config('dispensart.seed_user_password');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        if (app()->isProduction()) {
            return null;
        }

        /** @var string */
        return config('dispensart.seed_user_password_dev_default');
    }
}
