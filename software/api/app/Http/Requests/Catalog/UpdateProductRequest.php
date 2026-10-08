<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modificación parcial de producto (catalog "Modificación de productos").
 */
final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->product());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('products', 'code')->ignore($this->product())],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'presentation' => ['sometimes', 'nullable', 'string', 'max:150'],
            'is_controlled' => ['sometimes', 'boolean'],
        ];
    }

    public function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }
}
