<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Límite de intentos fallidos de autorizador alcanzado para el par dispensador + correo (RN-05, design D6).
 * HTTP 429 too_many_attempts con Retry-After.
 */
final class TooManyAuthorizerAttempts extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many authorizer attempts.');
    }
}
