<?php

namespace App\Services\Assistant\Evaluation;

use RuntimeException;

/**
 * El conjunto de evaluación no existe, no es JSON válido o tiene una entrada defectuosa. El mensaje, en español,
 * nombra la entrada; el comando sale con código 2 sin imprimir total.
 */
final class EvaluationSetInvalid extends RuntimeException {}
