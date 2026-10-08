<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Prescripciones (RN-04): solo prescriptions.create (médico) las crea.
 */
final class PrescriptionPolicy
{
    public function create(User $user): bool
    {
        return $user->role->allows(Ability::PrescriptionsCreate->value);
    }
}
