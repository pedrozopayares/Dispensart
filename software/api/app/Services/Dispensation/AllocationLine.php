<?php

namespace App\Services\Dispensation;

use Carbon\CarbonImmutable;

/**
 * Unidades que la asignación FEFO toma de un lote.
 */
final readonly class AllocationLine
{
    public function __construct(
        public int $lotId,
        public string $lotCode,
        public CarbonImmutable $expiresOn,
        public int $quantity,
    ) {}
}
