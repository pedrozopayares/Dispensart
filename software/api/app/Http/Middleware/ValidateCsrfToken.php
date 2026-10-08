<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Verificación CSRF sin atajos (design D2, identity-access "Token CSRF para la SPA").
 *
 * - En pruebas el framework la omite; aquí no, para que las pruebas de sesión puedan fallar.
 * - Toda escritura exige X-XSRF-TOKEN: no basta Sec-Fetch-Site same-origin. Sin lista except.
 */
final class ValidateCsrfToken extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }

    protected function hasValidOrigin($request): bool
    {
        return false;
    }
}
