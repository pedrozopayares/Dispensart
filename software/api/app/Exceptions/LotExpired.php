<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Operación sobre un lote vencido (RN-01): ingreso por ajuste, creación o despacho de traslado, devolución
 * al origen. HTTP 422 lot_expired.
 */
final class LotExpired extends RuntimeException {}
