<?php

namespace App\Services\Transfers;

use App\Enums\TransferStatus;

/**
 * Resultado de una recepción: estado final y faltante por línea (solo líneas con faltante > 0).
 */
final readonly class ReceiptOutcome
{
    /**
     * @param  array<int, int>  $shortages  id de línea → faltante
     */
    public function __construct(
        public TransferStatus $status,
        public array $shortages,
    ) {}
}
