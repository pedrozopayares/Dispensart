<?php

namespace App\Services\Assistant\Llm;

use App\Models\Product;
use App\Models\Warehouse;

/**
 * Nombres de bodegas y productos del catálogo para el modo simulado (design D10). Nunca pacientes ni usuarios.
 */
final class CatalogVocabulary
{
    /**
     * @return list<string>
     */
    public function warehouses(): array
    {
        /** @var list<string> */
        return Warehouse::query()->orderBy('id')->pluck('name')->all();
    }

    /**
     * @return list<string>
     */
    public function products(): array
    {
        /** @var list<string> */
        return Product::query()->orderBy('id')->pluck('name')->all();
    }
}
