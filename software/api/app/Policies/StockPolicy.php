<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Existencias: lectura con inventory.view; ajustes solo con inventory.adjust (regente) (design D9).
 * El rol sale del usuario cargado de la base en esta petición.
 */
final class StockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Ability::InventoryView->value);
    }

    public function adjust(User $user): bool
    {
        return $user->role->allows(Ability::InventoryAdjust->value);
    }
}
