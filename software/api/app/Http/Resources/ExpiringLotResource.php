<?php

namespace App\Http\Resources;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Existencia de un lote que vence en la ventana de alerta (inventory-alerts «Alerta de vencimiento»).
 * `days_to_expiry` = vencimiento − hoy en Bogotá: 0 vence hoy, negativo ya vencido.
 *
 * @mixin Stock
 */
final class ExpiringLotResource extends JsonResource
{
    /**
     * @return array{warehouse: WarehouseResource, product: ProductSummaryResource, lot: LotSummaryResource, quantity: int, days_to_expiry: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'warehouse' => new WarehouseResource($this->warehouse),
            'product' => new ProductSummaryResource($this->product),
            'lot' => new LotSummaryResource($this->lot),
            'quantity' => $this->quantity,
            'days_to_expiry' => (int) $this->getAttribute('days_to_expiry'),
        ];
    }
}
