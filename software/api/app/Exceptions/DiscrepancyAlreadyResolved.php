<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La discrepancia ya tiene resolución; no se resuelve dos veces. HTTP 409 discrepancy_already_resolved.
 */
final class DiscrepancyAlreadyResolved extends RuntimeException {}
