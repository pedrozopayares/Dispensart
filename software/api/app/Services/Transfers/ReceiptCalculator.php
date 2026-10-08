<?php

namespace App\Services\Transfers;

use App\Enums\TransferStatus;
use LogicException;

/**
 * Cálculo puro de la recepción (design D6): todo recibido → RECIBIDO; si no, RECIBIDO_PARCIAL con el faltante de
 * cada línea. La sobre-recepción y las líneas ajenas solo se rechazan como 422 en el FormRequest; aquí son un
 * defecto (500) y la base las respalda (transfer_lines_received_range).
 */
final class ReceiptCalculator
{
    /**
     * @param  array<int, int>  $quantities  id de línea → cantidad despachada
     * @param  array<int, int>  $received  id de línea → cantidad recibida
     */
    public function compute(array $quantities, array $received): ReceiptOutcome
    {
        $lineIds = array_keys($quantities);
        $receivedIds = array_keys($received);
        sort($lineIds);
        sort($receivedIds);
        if ($lineIds !== $receivedIds) {
            throw new LogicException('La recepción debe traer exactamente las líneas del traslado.');
        }

        $shortages = [];
        foreach ($quantities as $lineId => $quantity) {
            $got = $received[$lineId];
            if ($got < 0 || $got > $quantity) {
                throw new LogicException("Cantidad recibida fuera de rango en la línea {$lineId}.");
            }
            if ($got < $quantity) {
                $shortages[$lineId] = $quantity - $got;
            }
        }

        return new ReceiptOutcome(
            $shortages === [] ? TransferStatus::Received : TransferStatus::PartiallyReceived,
            $shortages,
        );
    }
}
