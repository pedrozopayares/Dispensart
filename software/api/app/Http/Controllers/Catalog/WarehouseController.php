<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveWarehouse;
use App\Http\Requests\Catalog\StoreWarehouseRequest;
use App\Http\Requests\Catalog\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Queries\CatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WarehouseController
{
    /**
     * Bodegas ordenadas por nombre (todo rol).
     */
    public function index(CatalogQuery $query): AnonymousResourceCollection
    {
        return WarehouseResource::collection($query->warehouses());
    }

    /**
     * Crea una bodega (solo admin).
     */
    public function store(StoreWarehouseRequest $request, SaveWarehouse $save): WarehouseResource
    {
        /** @var array{code: string, name: string} $data */
        $data = $request->validated();

        return new WarehouseResource($save->handle(new Warehouse, $data));
    }

    /**
     * Modifica parcialmente una bodega (solo admin).
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse, SaveWarehouse $save): WarehouseResource
    {
        /** @var array{code?: string, name?: string} $data */
        $data = $request->validated();

        return new WarehouseResource($save->handle($warehouse, $data));
    }
}
