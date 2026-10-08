<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La acción no está permitida desde el estado actual del traslado (RN-07). HTTP 409 invalid_transfer_transition.
 */
final class InvalidTransferTransition extends RuntimeException {}
