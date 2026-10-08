<?php

namespace App\Services\Dispensation;

/**
 * Resultado FEFO de un producto en una bodega: lotes tomados en orden, disponible no vencido, faltante y
 * unidades vencidas excluidas (dispensation "Vista previa de asignación FEFO").
 */
final readonly class Allocation
{
    /**
     * @param  list<AllocationLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $available,
        public int $shortage,
        public int $expiredExcluded,
    ) {}

    public function isFulfilled(): bool
    {
        return $this->shortage === 0;
    }
}
