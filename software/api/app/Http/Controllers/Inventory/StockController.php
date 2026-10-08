<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\ListStockRequest;
use App\Http\Resources\StockResource;
use App\Queries\InventoryQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StockController
{
    /**
     * Existencias con cantidad mayor que 0, filtrables por bodega, producto y lote (inventory.view).
     */
    public function __invoke(ListStockRequest $request, InventoryQuery $query): AnonymousResourceCollection
    {
        return StockResource::collection($query->stock($request->filters()));
    }
}
