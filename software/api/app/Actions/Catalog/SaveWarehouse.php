<?php

namespace App\Actions\Catalog;

use App\Models\Warehouse;

/**
 * Alta o modificación parcial de bodega: un campo ausente queda sin cambios.
 */
final class SaveWarehouse
{
    /**
     * @param  array{code?: string, name?: string}  $data
     */
    public function handle(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->fill($data)->save();

        return $warehouse;
    }
}
