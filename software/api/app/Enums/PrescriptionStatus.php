<?php

namespace App\Enums;

/**
 * Estado calculado de una prescripción (prescriptions "Estado de la prescripción"). Nunca se guarda.
 */
enum PrescriptionStatus: string
{
    case Active = 'vigente';
    case Expired = 'vencida';
    case Exhausted = 'agotada';
}
