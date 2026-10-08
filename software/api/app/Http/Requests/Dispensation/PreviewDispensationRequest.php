<?php

namespace App\Http\Requests\Dispensation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vista previa FEFO. El permiso (dispensations.create) lo exige la ruta (`can`), antes de validar. Cada ítem
 * pertenece a la prescripción indicada y no se repite.
 */
class PreviewDispensationRequest extends FormRequest
{
    public const MAX_ITEMS = 20;

    public const MAX_QUANTITY = 1_000_000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `bail`: un id no entero no llega a la consulta `exists` (PostgreSQL rechazaría el texto en bigint).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $prescriptionId = filter_var($this->input('prescription_id'), FILTER_VALIDATE_INT) ?: 0;

        return [
            'prescription_id' => ['bail', 'required', 'integer', 'min:1', 'exists:prescriptions,id'],
            'warehouse_id' => ['bail', 'required', 'integer', 'min:1', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['required', 'array'],
            'items.*.prescription_item_id' => [
                'bail', 'required', 'integer', 'min:1', 'distinct',
                Rule::exists('prescription_items', 'id')->where('prescription_id', $prescriptionId),
            ],
            'items.*.quantity' => ['bail', 'required', 'integer', 'between:1,'.self::MAX_QUANTITY],
        ];
    }

    /**
     * Datos de la dispensación, tipados: solo esto llega a la acción (y a la huella de idempotencia).
     *
     * @return array{prescription_id: int, warehouse_id: int, items: list<array{prescription_item_id: int, quantity: int}>}
     */
    public function dispensation(): array
    {
        /** @var array{prescription_id: int|string, warehouse_id: int|string, items: list<array{prescription_item_id: int|string, quantity: int|string}>} $data */
        $data = $this->validated();

        return [
            'prescription_id' => (int) $data['prescription_id'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'items' => array_map(fn (array $item): array => [
                'prescription_item_id' => (int) $item['prescription_item_id'],
                'quantity' => (int) $item['quantity'],
            ], $data['items']),
        ];
    }
}
