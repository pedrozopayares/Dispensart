<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Correo inexistente o contraseña incorrecta: una sola excepción, una sola respuesta (sin oráculo de correos).
 */
final class InvalidCredentials extends RuntimeException {}
