<?php

namespace App\Http\Requests\Prescriptions;

use App\Models\Prescription;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de prescripción (prescriptions "Creación de prescripciones por el médico"): solo prescriptions.create.
 * `valid_until` ≥ hoy en Bogotá; 1 a 20 ítems sin producto repetido. El médico nunca se lee del cuerpo: solo
 * `prescription()` llega a la acción.
 */
final class StorePrescriptionRequest extends FormRequest
{
    public const MAX_ITEMS = 20;

    public const MAX_QUANTITY = 1_000_000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Prescription::class);
    }

    /**
     * `bail`: un id no entero no llega a la consulta `exists` (PostgreSQL rechazaría el texto en bigint).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['bail', 'required', 'integer', 'min:1', 'exists:patients,id'],
            'valid_until' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:'.BusinessCalendar::today()->toDateString()],
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['bail', 'required', 'integer', 'min:1', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['bail', 'required', 'integer', 'between:1,'.self::MAX_QUANTITY],
        ];
    }

    /**
     * @return array{patient_id: int, valid_until: string, items: list<array{product_id: int, quantity: int}>}
     */
    public function prescription(): array
    {
        /** @var array{patient_id: int|string, valid_until: string, items: list<array{product_id: int|string, quantity: int|string}>} $data */
        $data = $this->validated();

        return [
            'patient_id' => (int) $data['patient_id'],
            'valid_until' => $data['valid_until'],
            'items' => array_map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ], $data['items']),
        ];
    }
}
