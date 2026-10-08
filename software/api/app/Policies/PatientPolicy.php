<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\User;

/**
 * Pacientes (RN-10): lectura con patients.view (admin sin acceso). Ver datos en claro es una lista de
 * permitidos (design D7): todo otro rol con lectura, como el auditor, los recibe enmascarados; un rol
 * futuro no ve en claro por defecto.
 */
final class PatientPolicy
{
    private const IDENTIFIABLE = [Role::Medico, Role::AuxiliarFarmacia, Role::RegenteFarmacia];

    public function viewAny(User $user): bool
    {
        return $user->role->allows(Ability::PatientsView->value);
    }

    public function view(User $user): bool
    {
        return $user->role->allows(Ability::PatientsView->value);
    }

    public function viewIdentifiable(User $user): bool
    {
        return $this->view($user) && in_array($user->role, self::IDENTIFIABLE, true);
    }
}
