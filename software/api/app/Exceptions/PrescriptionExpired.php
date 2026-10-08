<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La prescripción está vencida: no admite dispensación (RN-04). HTTP 422 prescription_expired.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class PrescriptionExpired extends RuntimeException {}
