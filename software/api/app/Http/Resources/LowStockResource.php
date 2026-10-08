<?php

namespace App\Http\Resources;

use App\Models\StockMinimum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Par bodega + producto bajo su stock mínimo (inventory-alerts «Alerta de stock bajo mínimo»).
 * `available_quantity` = suma de existencias no vencidas en esa bodega.
 *
 * @mixin StockMinimum
 */
final class LowStockResource extends JsonResource
{
    /**
     * @return array{warehouse: WarehouseResource, product: ProductSummaryResource, minimum_quantity: int, available_quantity: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'warehouse' => new WarehouseResource($this->warehouse),
            'product' => new ProductSummaryResource($this->product),
            'minimum_quantity' => $this->minimum_quantity,
            'available_quantity' => (int) $this->getAttribute('available_quantity'),
        ];
    }
}
