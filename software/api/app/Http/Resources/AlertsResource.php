<?php

namespace App\Http\Resources;

use App\Models\Stock;
use App\Models\StockMinimum;
use App\Queries\InventoryAlerts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Las dos listas de alertas bajo `data` (design D8). Una lista sin alertas se serializa `[]`, nunca se omite.
 *
 * @mixin InventoryAlerts
 */
final class AlertsResource extends JsonResource
{
    /**
     * @return array{expiring_lots: list<ExpiringLotResource>, low_stock: list<LowStockResource>}
     */
    public function toArray(Request $request): array
    {
        return [
            'expiring_lots' => $this->expiringLots
                ->map(fn (Stock $stock): ExpiringLotResource => new ExpiringLotResource($stock))
                ->values()->all(),
            'low_stock' => $this->lowStock
                ->map(fn (StockMinimum $minimum): LowStockResource => new LowStockResource($minimum))
                ->values()->all(),
        ];
    }
}
