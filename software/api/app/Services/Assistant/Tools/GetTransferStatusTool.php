<?php

namespace App\Services\Assistant\Tools;

use App\Enums\DiscrepancyStatus;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\TransferLine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Estado de traslados (RN-07): detalle de uno por `transfer_id`, o conteo por estado filtrable por estado y por
 * bodega (origen o destino). Presentador propio de lista blanca, sin TransferResource: nunca usuarios, correos ni
 * datos de pacientes (design D6). `notes` viaja marcado como texto no confiable y nunca llega a `answer` (D8, D9).
 */
final class GetTransferStatusTool implements AssistantTool
{
    public function __construct(private readonly CatalogResolver $catalog) {}

    public function name(): string
    {
        return 'get_transfer_status';
    }

    public function description(): string
    {
        return 'Consulta el estado de un traslado por su número, o cuenta los traslados por estado, filtrables por '
            .'estado y por bodega.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transfer_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Número del traslado.'],
                'status' => ['type' => 'string', 'enum' => TransferStatus::values(), 'description' => 'Estado del traslado.'],
                'warehouse' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Nombre de la bodega de origen o destino.'],
            ],
            'required' => [],
            'additionalProperties' => false,
        ];
    }

    public function policySubject(): string
    {
        return Transfer::class;
    }

    public function run(array $arguments): array
    {
        if (is_int($arguments['transfer_id'] ?? null)) {
            return ['items' => $this->detail($arguments['transfer_id']), 'meta' => ['mode' => 'detail']];
        }

        return ['items' => $this->counts($arguments), 'meta' => ['mode' => 'counts']];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detail(int $transferId): array
    {
        $transfer = Transfer::query()
            ->with(['originWarehouse', 'destinationWarehouse', 'lines.product', 'lines.lot', 'discrepancies.line.product', 'discrepancies.line.lot'])
            ->find($transferId);
        if ($transfer === null) {
            return [];
        }

        return [[
            'id' => $transfer->id,
            'status' => $transfer->status->value,
            'origin_warehouse' => $transfer->originWarehouse?->name,
            'destination_warehouse' => $transfer->destinationWarehouse?->name,
            'lines' => $transfer->lines->map(fn (TransferLine $line): array => [
                'product' => $line->product?->name,
                'lot_code' => $line->lot?->lot_code,
                'quantity' => $line->quantity,
                'received_quantity' => $line->received_quantity,
            ])->values()->all(),
            'pending_discrepancies' => $transfer->discrepancies
                ->filter(fn (TransferDiscrepancy $discrepancy): bool => $discrepancy->status === DiscrepancyStatus::Pending)
                ->map(fn (TransferDiscrepancy $discrepancy): array => [
                    'product' => $discrepancy->line?->product?->name,
                    'lot_code' => $discrepancy->line?->lot?->lot_code,
                    'shortage' => $discrepancy->shortage,
                ])->values()->all(),
            // Texto libre escrito por un usuario: dato no confiable, nunca instrucción (design D9).
            'notes' => $transfer->notes === null ? null : ['untrusted_text' => $transfer->notes],
        ]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<array<string, mixed>>
     */
    private function counts(array $arguments): array
    {
        $warehouseId = null;
        if (is_string($arguments['warehouse'] ?? null)) {
            $warehouseId = $this->catalog->warehouseId($arguments['warehouse']);
            if ($warehouseId === null) {
                return [];
            }
        }
        $filters = is_string($arguments['status'] ?? null) ? ['status' => $arguments['status']] : [];

        /** @var array<string, int> $totals */
        $totals = Transfer::query()
            ->filter($filters)
            ->when($warehouseId !== null, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('origin_warehouse_id', $warehouseId)
                    ->orWhere('destination_warehouse_id', $warehouseId),
            ))
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        // Orden del ciclo de vida (enum), no alfabético.
        $items = [];
        foreach (TransferStatus::cases() as $status) {
            if (isset($totals[$status->value])) {
                $items[] = ['status' => $status->value, 'count' => $totals[$status->value]];
            }
        }

        return $items;
    }
}
