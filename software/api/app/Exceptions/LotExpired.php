<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ingreso de unidades a un lote vencido (RN-01). HTTP 422 lot_expired.
 */
final class LotExpired extends RuntimeException {}
