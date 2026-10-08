<?php

namespace App\Enums;

/**
 * Acción registrada en la bitácora de acceso a pacientes (audit-trail).
 */
enum PatientAccessAction: string
{
    case View = 'view';
    case Search = 'search';
}
