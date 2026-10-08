<?php

namespace App\Services\Assistant\Tools;

use App\Models\Stock;
use App\Models\StockMinimum;
use App\Queries\AlertQuery;

/**
 * Pares bodega + producto bajo su stock mínimo (RN-11). Reutiliza AlertQuery::lowStock de S5 sin repetir su regla
 * (disponible sin vencidos estrictamente menor que el mínimo). Misma Policy que la ruta de alertas.
 */
final class GetLowStockAlertsTool implements AssistantTool
{
    public function __construct(
        private readonly AlertQuery $alerts,
        private readonly CatalogResolver $catalog,
    ) {}

    public function name(): string
    {
        return 'get_low_stock_alerts';
    }

    public function description(): string
    {
        return 'Lista los productos por debajo de su stock mínimo en cada bodega, filtrables por bodega.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'warehouse' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Nombre de la bodega.'],
            ],
            'required' => [],
            'additionalProperties' => false,
        ];
    }

    public function policySubject(): string
    {
        return Stock::class;
    }

    public function run(array $arguments): array
    {
        $filters = $this->catalog->filters($arguments);
        if ($filters === null) {
            return ['items' => [], 'meta' => []];
        }

        $items = $this->alerts
            ->lowStock($filters['warehouse_id'] ?? null)
            ->map(fn (StockMinimum $minimum): array => [
                'warehouse' => $minimum->warehouse?->name,
                'product' => $minimum->product?->name,
                'minimum' => $minimum->minimum_quantity,
                'available' => (int) $minimum->getAttribute('available_quantity'),
            ])
            ->values()
            ->all();

        return ['items' => $items, 'meta' => []];
    }
}
