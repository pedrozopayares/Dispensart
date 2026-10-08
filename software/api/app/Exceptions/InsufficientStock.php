<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La operación dejaría una existencia negativa o la existencia no existe (RN-03). HTTP 409
 * insufficient_stock, sin cantidades en la respuesta.
 */
final class InsufficientStock extends RuntimeException {}
