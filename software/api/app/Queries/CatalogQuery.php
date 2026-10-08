<?php

namespace App\Queries;

use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecturas del catálogo y de usuarios con su orden contractual (design API contract). Sin paginación en S1.
 */
final class CatalogQuery
{
    /**
     * @return Collection<int, Warehouse>
     */
    public function warehouses(): Collection
    {
        return Warehouse::query()->orderBy('name')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        return Product::query()->orderBy('name')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Lot>
     */
    public function lots(?int $productId): Collection
    {
        return Lot::query()
            ->when($productId !== null, fn ($query) => $query->where('product_id', $productId))
            ->orderBy('expires_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return User::query()->orderBy('name')->orderBy('id')->get();
    }
}
