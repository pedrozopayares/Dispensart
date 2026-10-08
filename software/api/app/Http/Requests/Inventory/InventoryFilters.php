<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros opcionales por bodega, producto y lote, comunes a existencias y kardex (combinados con Y).
 */
final class InventoryFilters
{
    private const FIELDS = ['warehouse_id', 'product_id', 'lot_id'];

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return array_fill_keys(self::FIELDS, ['sometimes', 'integer', 'min:1']);
    }

    /**
     * Solo los filtros presentes y ya validados, como enteros.
     *
     * @return array{warehouse_id?: int, product_id?: int, lot_id?: int}
     */
    public static function from(FormRequest $request): array
    {
        $filters = [];
        foreach (self::FIELDS as $field) {
            if ($request->has($field)) {
                $filters[$field] = $request->integer($field);
            }
        }

        return $filters;
    }
}
