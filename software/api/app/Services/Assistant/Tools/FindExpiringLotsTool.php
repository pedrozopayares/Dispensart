<?php

namespace App\Services\Assistant\Tools;

use App\Models\Stock;
use App\Queries\AlertQuery;
use App\Support\BusinessCalendar;

/**
 * Lotes con existencia que vencen en `days` días o menos (por defecto 90, RN-11), ya vencidos incluidos y marcados
 * (RN-01). Reutiliza la consulta de vencimiento de S5: una sola regla de ventana en todo el producto (design D6).
 */
final class FindExpiringLotsTool implements AssistantTool
{
    public const DEFAULT_DAYS = AlertQuery::EXPIRY_WINDOW_DAYS;

    public function __construct(
        private readonly AlertQuery $alerts,
        private readonly CatalogResolver $catalog,
    ) {}

    public function name(): string
    {
        return 'find_expiring_lots';
    }

    public function description(): string
    {
        return 'Lista los lotes con existencia que vencen en los próximos días (incluye los ya vencidos), '
            .'filtrables por producto y por bodega.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'description' => 'Plazo en días desde hoy. Por defecto 90.'],
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
        $days = is_int($arguments['days'] ?? null) ? $arguments['days'] : self::DEFAULT_DAYS;
        $meta = ['days' => $days];
        $filters = $this->catalog->filters($arguments);
        if ($filters === null) {
            return ['items' => [], 'meta' => $meta];
        }

        $today = BusinessCalendar::today();
        $items = $this->alerts
            ->expiringLots($filters['warehouse_id'] ?? null, $days, $filters['product_id'] ?? null)
            ->map(fn (Stock $stock): array => [
                'warehouse' => $stock->warehouse?->name,
                'product' => $stock->product?->name,
                'lot_code' => $stock->lot?->lot_code,
                'expires_on' => $stock->lot?->expires_on->toDateString(),
                'quantity' => $stock->quantity,
                'is_expired' => (bool) $stock->lot?->isExpiredOn($today),
            ])
            ->values()
            ->all();

        return ['items' => $items, 'meta' => $meta];
    }
}
