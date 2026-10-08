<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La prescripción no tiene pendiente en ningún ítem (RN-04). HTTP 422 prescription_exhausted.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class PrescriptionExhausted extends RuntimeException {}
