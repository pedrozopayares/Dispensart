<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Dispensaciones (RN-04): vista previa y dispensación con dispensations.create (auxiliar y regente).
 */
final class DispensationPolicy
{
    public function create(User $user): bool
    {
        return $user->role->allows(Ability::DispensationsCreate->value);
    }
}
