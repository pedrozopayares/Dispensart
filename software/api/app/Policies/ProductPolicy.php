<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Catálogo: lectura para todo rol con catalog.view; escritura solo con catalog.manage (design D4).
 * El rol sale del usuario cargado de la base en esta petición.
 */
final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Ability::CatalogView->value);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Ability::CatalogManage->value);
    }

    public function update(User $user): bool
    {
        return $user->role->allows(Ability::CatalogManage->value);
    }
}
