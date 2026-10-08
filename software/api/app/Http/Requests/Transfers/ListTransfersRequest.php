<?php

namespace App\Http\Requests\Transfers;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Consulta paginada de traslados (transfers "Consulta de traslados"): transfers.view; filtros por estado y
 * bodegas combinados con Y; per_page 1–100, 50 por defecto; page 1–1 000 000 (una página mayor desborda el
 * desplazamiento SQL y daría 500, no 422).
 */
final class ListTransfersRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public const MAX_PAGE = 1_000_000;

    private const FILTERS = ['origin_warehouse_id', 'destination_warehouse_id'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Transfer::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(TransferStatus::values())],
            'origin_warehouse_id' => ['sometimes', 'integer', 'min:1'],
            'destination_warehouse_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PAGE],
        ];
    }

    /**
     * @return array{status?: string, origin_warehouse_id?: int, destination_warehouse_id?: int}
     */
    public function filters(): array
    {
        $filters = [];
        if ($this->has('status')) {
            $filters['status'] = $this->string('status')->toString();
        }
        foreach (self::FILTERS as $field) {
            if ($this->has($field)) {
                $filters[$field] = $this->integer($field);
            }
        }

        return $filters;
    }

    public function perPage(): int
    {
        return $this->has('per_page') ? $this->integer('per_page') : self::DEFAULT_PER_PAGE;
    }
}
