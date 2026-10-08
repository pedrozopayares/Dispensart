<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El usuario reutilizó una clave de idempotencia con otro cuerpo (RN-09). HTTP 422 idempotency_key_reused.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class IdempotencyKeyReused extends RuntimeException {}
