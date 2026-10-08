<?php

namespace App\Http\Requests\Inventory;

use App\Models\KardexMovement;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta paginada del kardex (kardex "Consulta del kardex"): mismos filtros que existencias, per_page 1–100.
 */
final class ListKardexRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', KardexMovement::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...InventoryFilters::rules(),
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{warehouse_id?: int, product_id?: int, lot_id?: int}
     */
    public function filters(): array
    {
        return InventoryFilters::from($this);
    }

    public function perPage(): int
    {
        return $this->has('per_page') ? $this->integer('per_page') : self::DEFAULT_PER_PAGE;
    }
}
