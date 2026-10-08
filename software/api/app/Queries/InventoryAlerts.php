<?php

namespace App\Queries;

use App\Models\Stock;
use App\Models\StockMinimum;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resultado de una consulta de alertas: las dos listas de RN-11, ya ordenadas por AlertQuery.
 */
final readonly class InventoryAlerts
{
    /**
     * @param  Collection<int, Stock>  $expiringLots
     * @param  Collection<int, StockMinimum>  $lowStock
     */
    public function __construct(
        public Collection $expiringLots,
        public Collection $lowStock,
    ) {}
}
