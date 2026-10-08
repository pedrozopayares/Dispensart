<?php

namespace App\Http\Resources;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Existencia por bodega + producto + lote (inventory "Consulta de existencias").
 *
 * @mixin Stock
 */
final class StockResource extends JsonResource
{
    /**
     * @return array{id: int, quantity: int, warehouse: WarehouseResource, product: ProductSummaryResource, lot: LotSummaryResource}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'warehouse' => new WarehouseResource($this->warehouse),
            'product' => new ProductSummaryResource($this->product),
            'lot' => new LotSummaryResource($this->lot),
        ];
    }
}
