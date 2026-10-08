<?php

namespace App\Services\Transfers;

use App\Models\Transfer;

/**
 * Bloqueo de la fila del traslado (design D4): toda acción que cambia su estado relee aquí, dentro de su
 * transacción, el estado vigente. Dos acciones simultáneas sobre el mismo traslado se serializan y la segunda ve
 * el estado que dejó la primera. Es la única cabecera que se bloquea antes de las existencias.
 */
final class TransferLocker
{
    public function lock(int $transferId): Transfer
    {
        return Transfer::query()->whereKey($transferId)->lockForUpdate()->firstOrFail();
    }
}
