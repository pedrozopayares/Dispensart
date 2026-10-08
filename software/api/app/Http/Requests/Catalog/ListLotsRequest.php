<?php

namespace App\Http\Requests\Catalog;

use App\Models\Lot;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de lotes con filtro opcional por producto (catalog "Consulta de lotes").
 */
final class ListLotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Lot::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function productId(): ?int
    {
        return $this->has('product_id') ? $this->integer('product_id') : null;
    }
}
