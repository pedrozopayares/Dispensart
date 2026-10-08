<?php

namespace App\Actions\Identity;

use App\Exceptions\InvalidCredentials;
use App\Exceptions\TooManyLoginAttempts;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Inicio de sesión por cookie (identity-access "Inicio de sesión", design D3).
 *
 * Límite: 5 fallos por correo + IP en 60 s; un éxito reinicia el contador. Correo inexistente y contraseña
 * incorrecta dan la misma excepción y cuestan lo mismo (hash ficticio). La sesión guarda solo el id del
 * usuario: el rol se lee de la base en cada petición.
 */
final class LoginAction
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    private static ?string $dummyHash = null;

    public function handle(string $email, string $password, string $ip, Session $session): User
    {
        $email = self::normalizeEmail($email);
        // Sin correo en claro en el almacén del limitador.
        $key = 'login|'.hash('sha256', $email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyLoginAttempts(RateLimiter::availableIn($key));
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            // Mismo costo que una verificación real, para no revelar qué correos existen.
            Hash::check($password, self::$dummyHash ??= Hash::make(Str::random(40)));
        }

        if ($user === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw new InvalidCredentials;
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user);
        $session->regenerate();

        return $user;
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
