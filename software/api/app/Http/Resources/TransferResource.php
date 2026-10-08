<?php

namespace App\Http\Resources;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\TransferLine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle del traslado (transfers "Consulta de traslados"): estado, bodegas, observaciones como dato, actor y
 * fecha de cada transición (ISO 8601 UTC), líneas con cantidad enviada y recibida, y discrepancias. De cada
 * usuario solo id y nombre.
 *
 * @mixin Transfer
 */
final class TransferResource extends JsonResource
{
    /**
     * @return array{id: int, status: TransferStatus, notes: string|null, origin_warehouse: WarehouseResource, destination_warehouse: WarehouseResource, created_by: array{id: int, name: string}|null, created_at: string, requested_by: array{id: int, name: string}|null, requested_at: string|null, approved_by: array{id: int, name: string}|null, approved_at: string|null, dispatched_by: array{id: int, name: string}|null, dispatched_at: string|null, received_by: array{id: int, name: string}|null, received_at: string|null, voided_by: array{id: int, name: string}|null, voided_at: string|null, void_reason: string|null, lines: list<array{id: int, product: array{id: int, code: string, name: string}, lot: LotSummaryResource, quantity: int, received_quantity: int|null}>, discrepancies: list<TransferDiscrepancyResource>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'notes' => $this->notes,
            'origin_warehouse' => new WarehouseResource($this->originWarehouse),
            'destination_warehouse' => new WarehouseResource($this->destinationWarehouse),
            'created_by' => self::actor($this->creator),
            'created_at' => $this->created_at->toIso8601String(),
            'requested_by' => self::actor($this->requester),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'approved_by' => self::actor($this->approver),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'dispatched_by' => self::actor($this->dispatcher),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'received_by' => self::actor($this->receiver),
            'received_at' => $this->received_at?->toIso8601String(),
            'voided_by' => self::actor($this->voider),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'lines' => $this->lines->map(fn (TransferLine $line): array => [
                'id' => $line->id,
                'product' => ['id' => $line->product->id, 'code' => $line->product->code, 'name' => $line->product->name],
                'lot' => new LotSummaryResource($line->lot),
                'quantity' => $line->quantity,
                'received_quantity' => $line->received_quantity,
            ])->values()->all(),
            'discrepancies' => $this->discrepancies
                ->map(fn (TransferDiscrepancy $discrepancy): TransferDiscrepancyResource => new TransferDiscrepancyResource($discrepancy))
                ->values()->all(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function actor(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
