<?php

namespace App\Http\Requests\Inventory;

use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de alertas (inventory-alerts «Consulta de alertas»). Quien lee existencias lee alertas: misma Policy
 * (`StockPolicy::viewAny`, inventory.view; design D5). Filtro entero ≥ 1 sin `exists`: un id inexistente da
 * listas vacías, no 422.
 */
final class ListAlertsRequest extends FormRequest
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
        return [
            'warehouse_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function warehouseId(): ?int
    {
        return $this->has('warehouse_id') ? $this->integer('warehouse_id') : null;
    }
}
