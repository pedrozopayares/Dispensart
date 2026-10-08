<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveProduct;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Queries\CatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController
{
    /**
     * Productos ordenados por nombre (todo rol).
     */
    public function index(CatalogQuery $query): AnonymousResourceCollection
    {
        return ProductResource::collection($query->products());
    }

    /**
     * Crea un producto (solo admin).
     */
    public function store(StoreProductRequest $request, SaveProduct $save): ProductResource
    {
        /** @var array{code: string, name: string, presentation?: string|null, is_controlled?: bool} $data */
        $data = $request->validated();

        return new ProductResource($save->handle(new Product, $data));
    }

    /**
     * Modifica parcialmente un producto (solo admin).
     */
    public function update(UpdateProductRequest $request, Product $product, SaveProduct $save): ProductResource
    {
        /** @var array{code?: string, name?: string, presentation?: string|null, is_controlled?: bool} $data */
        $data = $request->validated();

        return new ProductResource($save->handle($product, $data));
    }
}
