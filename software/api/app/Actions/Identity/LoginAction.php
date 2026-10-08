<?php

namespace App\Actions\Identity;

use App\Exceptions\InvalidCredentials;
use App\Exceptions\TooManyLoginAttempts;
use App\Models\User;
use App\Services\Identity\CredentialVerifier;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Inicio de sesión por cookie (identity-access "Inicio de sesión", design D3).
 *
 * Límite: 5 fallos por correo + IP en 60 s; un éxito reinicia el contador. Correo inexistente y contraseña
 * incorrecta dan la misma excepción y cuestan lo mismo (CredentialVerifier, hash ficticio). La sesión guarda
 * solo el id del usuario: el rol se lee de la base en cada petición.
 */
final class LoginAction
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function __construct(private readonly CredentialVerifier $credentials) {}

    public function handle(string $email, string $password, string $ip, Session $session): User
    {
        $email = self::normalizeEmail($email);
        // Sin correo en claro en el almacén del limitador.
        $key = 'login|'.hash('sha256', $email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyLoginAttempts(RateLimiter::availableIn($key));
        }

        $user = $this->credentials->verify($email, $password);

        if ($user === null) {
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
        return CredentialVerifier::normalizeEmail($email);
    }
}
