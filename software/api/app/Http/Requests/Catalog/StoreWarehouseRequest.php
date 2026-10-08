<?php

namespace App\Http\Requests\Catalog;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de bodega (catalog "Alta de bodegas"): solo catalog.manage.
 */
final class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Warehouse::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')],
            'name' => ['required', 'string', 'max:120', Rule::unique('warehouses', 'name')],
        ];
    }
}
