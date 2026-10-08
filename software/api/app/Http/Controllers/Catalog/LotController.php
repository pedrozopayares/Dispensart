<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Requests\Catalog\ListLotsRequest;
use App\Http\Resources\LotResource;
use App\Queries\CatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LotController
{
    /**
     * Lotes por vencimiento ascendente y luego id, con filtro opcional por producto (todo rol).
     */
    public function __invoke(ListLotsRequest $request, CatalogQuery $query): AnonymousResourceCollection
    {
        return LotResource::collection($query->lots($request->productId()));
    }
}
