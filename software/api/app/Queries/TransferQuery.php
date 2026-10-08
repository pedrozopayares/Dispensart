<?php

namespace App\Queries;

use App\Models\Transfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lecturas de traslados con su orden contractual (transfers "Consulta de traslados"). Sin bloqueos.
 */
final class TransferQuery
{
    private const DETAIL = [
        'originWarehouse', 'destinationWarehouse', 'creator:id,name', 'requester:id,name', 'approver:id,name',
        'dispatcher:id,name', 'receiver:id,name', 'voider:id,name', 'lines.product', 'lines.lot',
        'discrepancies.line', 'discrepancies.resolver:id,name',
    ];

    /**
     * Del más reciente al más antiguo (fecha de creación, luego id), paginados.
     *
     * @param  array{status?: string, origin_warehouse_id?: int, destination_warehouse_id?: int}  $filters
     * @return LengthAwarePaginator<int, Transfer>
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return Transfer::query()
            ->filter($filters)
            ->with(['originWarehouse', 'destinationWarehouse', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function detail(int $transferId): Transfer
    {
        return Transfer::query()->with(self::DETAIL)->findOrFail($transferId);
    }
}
