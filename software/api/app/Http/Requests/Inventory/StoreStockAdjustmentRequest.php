<?php

namespace App\Http\Requests\Inventory;

use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajuste de inventario (inventory "Ajuste de inventario"): solo inventory.adjust. `reason` de solo espacios
 * llega como null (TrimStrings + ConvertEmptyStringsToNull) y falla `required`. Usuario, tipo, saldo y fecha
 * nunca se leen del cuerpo: solo `adjustment()` llega a la acción.
 */
final class StoreStockAdjustmentRequest extends FormRequest
{
    public const MAX_QUANTITY = 1_000_000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('adjust', Stock::class);
    }

    /**
     * `bail`: un id no entero no llega a la consulta `exists` (PostgreSQL rechazaría el texto en bigint).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['bail', 'required', 'integer', 'min:1', 'exists:warehouses,id'],
            'lot_id' => ['bail', 'required', 'integer', 'min:1', 'exists:lots,id'],
            'quantity' => ['bail', 'required', 'integer', 'not_in:0', 'between:-'.self::MAX_QUANTITY.','.self::MAX_QUANTITY],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array{warehouse_id: int, lot_id: int, quantity: int, reason: string}
     */
    public function adjustment(): array
    {
        /** @var array{warehouse_id: int|string, lot_id: int|string, quantity: int|string, reason: string} $data */
        $data = $this->validated();

        return [
            'warehouse_id' => (int) $data['warehouse_id'],
            'lot_id' => (int) $data['lot_id'],
            'quantity' => (int) $data['quantity'],
            'reason' => $data['reason'],
        ];
    }
}
