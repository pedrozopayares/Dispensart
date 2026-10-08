<?php

namespace App\Services\Assistant\Tools;

use App\Models\Stock;
use App\Queries\InventoryQuery;
use App\Support\BusinessCalendar;

/**
 * Existencias con cantidad > 0 por bodega y producto, con sus lotes marcados y el total disponible sin vencidos
 * (RN-01). Reutiliza la consulta de existencias de S2 (design D6).
 */
final class GetStockTool implements AssistantTool
{
    public function __construct(
        private readonly InventoryQuery $inventory,
        private readonly CatalogResolver $catalog,
    ) {}

    public function name(): string
    {
        return 'get_stock';
    }

    public function description(): string
    {
        return 'Consulta las existencias por bodega y producto, con el total disponible sin contar lotes vencidos.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Nombre del producto.'],
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

        $today = BusinessCalendar::today();
        /** @var array<string, array{warehouse: string|null, product: string|null, available_total: int, lots: list<array<string, mixed>>}> $groups */
        $groups = [];
        foreach ($this->inventory->stock($filters) as $stock) {
            $key = $stock->warehouse_id.':'.$stock->product_id;
            $groups[$key] ??= [
                'warehouse' => $stock->warehouse?->name,
                'product' => $stock->product?->name,
                'available_total' => 0,
                'lots' => [],
            ];
            $expired = (bool) $stock->lot?->isExpiredOn($today);
            if (! $expired) {
                $groups[$key]['available_total'] += $stock->quantity;
            }
            $groups[$key]['lots'][] = [
                'lot_code' => $stock->lot?->lot_code,
                'expires_on' => $stock->lot?->expires_on->toDateString(),
                'quantity' => $stock->quantity,
                'is_expired' => $expired,
            ];
        }

        return ['items' => array_values($groups), 'meta' => []];
    }
}
