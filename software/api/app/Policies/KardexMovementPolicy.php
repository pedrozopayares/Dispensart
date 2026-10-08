<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Kardex de solo lectura por la API: lectura con inventory.view. No hay capacidad de edición ni borrado.
 */
final class KardexMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Ability::InventoryView->value);
    }
}
