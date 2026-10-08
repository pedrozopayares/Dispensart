<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Correo inexistente, contraseña incorrecta o usuario sin controlled_drugs.authorize: una sola excepción, un solo cuerpo (sin oráculo de usuarios ni de roles, RN-05). HTTP 422 invalid_authorizer.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class InvalidAuthorizer extends RuntimeException {}
