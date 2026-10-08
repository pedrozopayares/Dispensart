<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falta la cabecera Idempotency-Key o no cumple el formato (RN-09). HTTP 422 invalid_idempotency_key.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class InvalidIdempotencyKey extends RuntimeException {}
