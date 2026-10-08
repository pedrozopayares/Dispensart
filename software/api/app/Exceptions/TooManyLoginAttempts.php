<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Límite de intentos fallidos de login alcanzado para el par correo + IP.
 */
final class TooManyLoginAttempts extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many login attempts.');
    }
}
