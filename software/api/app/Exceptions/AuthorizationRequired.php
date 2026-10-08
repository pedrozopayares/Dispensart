<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un ítem de control especial llegó sin correo o sin contraseña del autorizador (RN-05). HTTP 422 authorization_required.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class AuthorizationRequired extends RuntimeException {}
