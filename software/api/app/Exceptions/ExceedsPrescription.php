<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La cantidad pedida de un ítem supera su pendiente (RN-04). HTTP 422 exceeds_prescription.
 * Sin valores enviados en el mensaje ni en la respuesta.
 */
final class ExceedsPrescription extends RuntimeException {}
