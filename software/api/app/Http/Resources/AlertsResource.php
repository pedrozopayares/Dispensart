<?php

namespace App\Http\Resources;

use App\Models\Stock;
use App\Models\StockMinimum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Las dos listas de alertas bajo `data` (design D8). Una lista sin alertas se serializa `[]`, nunca se omite.
 */
final class AlertsResource extends JsonResource
{
    /**
     * @return array{expiring_lots: list<ExpiringLotResource>, low_stock: list<LowStockResource>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{expiring_lots: Collection<int, Stock>, low_stock: Collection<int, StockMinimum>} $alerts */
        $alerts = $this->resource;

        return [
            'expiring_lots' => $alerts['expiring_lots']
                ->map(fn (Stock $stock): ExpiringLotResource => new ExpiringLotResource($stock))
                ->values()->all(),
            'low_stock' => $alerts['low_stock']
                ->map(fn (StockMinimum $minimum): LowStockResource => new LowStockResource($minimum))
                ->values()->all(),
        ];
    }
}
