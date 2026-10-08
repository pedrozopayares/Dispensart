<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Quien creó y solicitó el traslado intenta aprobarlo (RN-08). HTTP 403 segregation_of_duties.
 */
final class SegregationOfDutiesViolation extends RuntimeException {}
