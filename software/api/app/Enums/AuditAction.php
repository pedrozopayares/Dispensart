<?php

namespace App\Enums;

/**
 * Operaciones sensibles de la bitácora (audit-trail "Bitácora de operaciones sensibles"), cada una con el
 * tipo de objeto al que se refiere. La base repite ambos conjuntos y su emparejamiento.
 */
enum AuditAction: string
{
    case PrescriptionCreated = 'prescription.created';
    case DispensationCreated = 'dispensation.created';
    case ControlledDrugAuthorized = 'controlled_drug.authorized';
    case ControlledDrugAuthorizationFailed = 'controlled_drug.authorization_failed';

    public function subjectType(): string
    {
        return match ($this) {
            self::PrescriptionCreated, self::ControlledDrugAuthorizationFailed => 'prescription',
            self::DispensationCreated, self::ControlledDrugAuthorized => 'dispensation',
        };
    }
}
