<?php

namespace App\Actions\Catalog;

use App\Models\Product;

/**
 * Alta o modificación parcial de producto: un campo ausente queda sin cambios; en el alta,
 * presentation ausente = null e is_controlled ausente = false.
 */
final class SaveProduct
{
    /**
     * @param  array{code?: string, name?: string, presentation?: string|null, is_controlled?: bool}  $data
     */
    public function handle(Product $product, array $data): Product
    {
        $product->fill($data)->save();

        return $product;
    }
}
