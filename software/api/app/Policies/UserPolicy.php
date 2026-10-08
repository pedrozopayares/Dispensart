<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Gestión de usuarios: solo con users.manage (admin).
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Ability::UsersManage->value);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Ability::UsersManage->value);
    }
}
