<?php

namespace App\Actions\Identity;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Cierre de sesión (identity-access "Cierre de sesión"): invalida la sesión en el servidor y regenera
 * el token CSRF; la cookie anterior deja de autenticar.
 */
final class LogoutAction
{
    public function handle(Session $session): void
    {
        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();
    }
}
