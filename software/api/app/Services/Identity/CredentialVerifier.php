<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Verificación de correo + contraseña sin oráculo (design D6 de S3, extraída de LoginAction sin cambiar su
 * comportamiento): un correo inexistente cuesta lo mismo que uno real (hash ficticio) y ambos fallos dan null.
 * Nunca registra ni guarda la contraseña.
 */
final class CredentialVerifier
{
    private static ?string $dummyHash = null;

    public function verify(string $email, string $password): ?User
    {
        $user = User::query()->where('email', self::normalizeEmail($email))->first();

        if ($user === null) {
            // Mismo costo que una verificación real, para no revelar qué correos existen.
            Hash::check($password, self::$dummyHash ??= Hash::make(Str::random(40)));

            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
