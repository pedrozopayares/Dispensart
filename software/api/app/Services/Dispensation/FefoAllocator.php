<?php

namespace App\Services\Dispensation;

use App\Models\Stock;
use Carbon\CarbonImmutable;

/**
 * Asignación FEFO pura (RN-01, RN-02, design D3): sobre las existencias de un producto en una bodega, toma
 * primero el lote que vence antes y, a igual vencimiento, el de menor id; excluye lotes vencidos hoy o antes
 * (Lot::isExpiredOn, único sitio de la regla: la consulta no filtra fecha) y existencias en 0.
 * Sin base de datos: recibe filas ya cargadas (con su lote) y no escribe nada.
 */
final class FefoAllocator
{
    /**
     * @param  iterable<Stock>  $stocks  existencias del producto en la bodega, con `lot` cargado
     */
    public function allocate(iterable $stocks, int $requested, CarbonImmutable $today): Allocation
    {
        $usable = [];
        $expired = 0;
        foreach ($stocks as $stock) {
            if ($stock->quantity <= 0) {
                continue;
            }
            if ($stock->lot->isExpiredOn($today)) {
                $expired += $stock->quantity;

                continue;
            }
            $usable[] = $stock;
        }

        usort($usable, fn (Stock $a, Stock $b): int => [$a->lot->expires_on->toDateString(), $a->lot->id]
            <=> [$b->lot->expires_on->toDateString(), $b->lot->id]);

        $lines = [];
        $pending = $requested;
        $available = 0;
        foreach ($usable as $stock) {
            $available += $stock->quantity;
            if ($pending === 0) {
                continue;
            }
            $take = min($pending, $stock->quantity);
            $lines[] = new AllocationLine($stock->lot->id, $stock->lot->lot_code, $stock->lot->expires_on, $take);
            $pending -= $take;
        }

        return new Allocation($lines, $available, $pending, $expired);
    }
}
