<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El autorizador de control especial es el propio dispensador (RN-05). HTTP 422 authorizer_must_differ.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class AuthorizerMustDiffer extends RuntimeException {}
