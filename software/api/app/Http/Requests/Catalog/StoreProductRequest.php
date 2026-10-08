<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de producto (catalog "Alta de productos"): solo catalog.manage. Campos ajenos (p. ej. role) se ignoran.
 */
final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Product::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('products', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'presentation' => ['sometimes', 'nullable', 'string', 'max:150'],
            'is_controlled' => ['sometimes', 'boolean'],
        ];
    }
}
