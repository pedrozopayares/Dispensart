<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Lotes de solo lectura en S1: nacen con las entradas de inventario (S2).
 */
final class LotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Ability::CatalogView->value);
    }
}
