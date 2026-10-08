<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Transfer;
use App\Models\User;

/**
 * Traslados (§ 3, RN-07; design D9) sobre el mapa de capacidades de S1, que no cambia. Solicitar solo el
 * creador; anular el creador o quien aprueba. La segregación de funciones (RN-08) no vive aquí: la aplica la
 * acción de aprobar, con su propio código (design D8).
 */
final class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Ability::TransfersView->value);
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $user->role->allows(Ability::TransfersView->value);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Ability::TransfersCreate->value);
    }

    public function request(User $user, Transfer $transfer): bool
    {
        return $user->role->allows(Ability::TransfersCreate->value) && $this->isCreator($user, $transfer);
    }

    public function approve(User $user, Transfer $transfer): bool
    {
        return $user->role->allows(Ability::TransfersApprove->value);
    }

    public function dispatch(User $user, Transfer $transfer): bool
    {
        return $user->role->allows(Ability::TransfersCreate->value);
    }

    public function receive(User $user, Transfer $transfer): bool
    {
        return $user->role->allows(Ability::TransfersReceive->value);
    }

    public function void(User $user, Transfer $transfer): bool
    {
        return $user->role->allows(Ability::TransfersApprove->value)
            || ($user->role->allows(Ability::TransfersCreate->value) && $this->isCreator($user, $transfer));
    }

    public function resolveDiscrepancy(User $user, Transfer $transfer): bool
    {
        return $user->role->allows(Ability::TransfersApprove->value);
    }

    private function isCreator(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id;
    }
}
