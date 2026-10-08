<?php

namespace App\Services\Assistant\Evaluation;

use RuntimeException;

/**
 * La base desechable de evaluación no se pudo crear (usuario sin CREATEDB, base caída) o su nombre coincide con
 * el de la base operativa. El comando sale con código 2 sin evaluar ni informar total.
 */
final class EvaluationDatabaseUnavailable extends RuntimeException {}
