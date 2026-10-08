<?php

namespace App\Http\Requests\Transfers;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de traslado (transfers "Creación de traslados"): solo transfers.create. Origen ≠ destino, 1 a 50 líneas sin
 * lote repetido. Estado, creador, aprobador y producto nunca se leen del cuerpo: solo `transfer()` llega a la
 * acción, con las claves conocidas de cada línea.
 */
final class StoreTransferRequest extends FormRequest
{
    public const MAX_LINES = 50;

    public const MAX_QUANTITY = 1_000_000;

    public const MAX_NOTES = 1000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Transfer::class);
    }

    /**
     * `bail`: un id no entero no llega a la consulta `exists` (PostgreSQL rechazaría el texto en bigint).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'origin_warehouse_id' => ['bail', 'required', 'integer', 'min:1', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['bail', 'required', 'integer', 'min:1', 'different:origin_warehouse_id', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:'.self::MAX_NOTES],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*' => ['required', 'array'],
            'lines.*.lot_id' => ['bail', 'required', 'integer', 'min:1', 'distinct', 'exists:lots,id'],
            'lines.*.quantity' => ['bail', 'required', 'integer', 'between:1,'.self::MAX_QUANTITY],
        ];
    }

    /**
     * @return array{origin_warehouse_id: int, destination_warehouse_id: int, notes: string|null, lines: list<array{lot_id: int, quantity: int}>}
     */
    public function transfer(): array
    {
        /** @var array{origin_warehouse_id: int|string, destination_warehouse_id: int|string, notes?: string|null, lines: list<array{lot_id: int|string, quantity: int|string}>} $data */
        $data = $this->validated();

        return [
            'origin_warehouse_id' => (int) $data['origin_warehouse_id'],
            'destination_warehouse_id' => (int) $data['destination_warehouse_id'],
            'notes' => $data['notes'] ?? null,
            'lines' => array_map(fn (array $line): array => [
                'lot_id' => (int) $line['lot_id'],
                'quantity' => (int) $line['quantity'],
            ], $data['lines']),
        ];
    }
}
