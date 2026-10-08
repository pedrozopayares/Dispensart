<?php

namespace App\Http\Requests\Catalog;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modificación parcial de bodega (catalog "Modificación de bodegas"): mismas reglas del alta; la bodega
 * puede conservar su propio código y nombre.
 */
final class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->warehouse());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($this->warehouse())],
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('warehouses', 'name')->ignore($this->warehouse())],
        ];
    }

    public function warehouse(): Warehouse
    {
        /** @var Warehouse */
        return $this->route('warehouse');
    }
}
