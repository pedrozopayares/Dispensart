<?php

namespace App\Http\Requests\Inventory;

use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ingreso de lote (inventory "Ingreso de lote", S18): solo inventory.adjust. Texto de solo espacios llega como null
 * (TrimStrings + ConvertEmptyStringsToNull) y falla `required`. Usuario, tipo, saldo y fecha nunca se leen del
 * cuerpo: solo `entry()` llega a la acción. El vencimiento distinto de un lote existente lo decide la acción.
 */
final class StoreStockEntryRequest extends FormRequest
{
    public const MAX_QUANTITY = 1_000_000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('receive', Stock::class);
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
            'product_id' => ['bail', 'required', 'integer', 'min:1', 'exists:products,id'],
            'lot_code' => ['required', 'string', 'max:50'],
            'expires_on' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['bail', 'required', 'integer', 'between:1,'.self::MAX_QUANTITY],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array{warehouse_id: int, product_id: int, lot_code: string, expires_on: string, quantity: int, reason: string}
     */
    public function entry(): array
    {
        /** @var array{warehouse_id: int|string, product_id: int|string, lot_code: string, expires_on: string, quantity: int|string, reason: string} $data */
        $data = $this->validated();

        return [
            'warehouse_id' => (int) $data['warehouse_id'],
            'product_id' => (int) $data['product_id'],
            'lot_code' => $data['lot_code'],
            'expires_on' => $data['expires_on'],
            'quantity' => (int) $data['quantity'],
            'reason' => $data['reason'],
        ];
    }
}
