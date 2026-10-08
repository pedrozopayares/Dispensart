<?php

namespace App\Http\Requests\Inventory;

use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de existencias (inventory "Consulta de existencias"). Filtros enteros sin `exists`: un id
 * inexistente da lista vacía, no 422 (design D9).
 */
final class ListStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Stock::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return InventoryFilters::rules();
    }

    /**
     * @return array{warehouse_id?: int, product_id?: int, lot_id?: int}
     */
    public function filters(): array
    {
        return InventoryFilters::from($this);
    }
}
