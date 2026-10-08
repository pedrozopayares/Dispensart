<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\ListKardexRequest;
use App\Http\Resources\KardexMovementResource;
use App\Queries\InventoryQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class KardexController
{
    /**
     * Movimientos del kardex, del más reciente al más antiguo, paginados (inventory.view).
     */
    public function __invoke(ListKardexRequest $request, InventoryQuery $query): AnonymousResourceCollection
    {
        return KardexMovementResource::collection($query->kardex($request->filters(), $request->perPage()));
    }
}
