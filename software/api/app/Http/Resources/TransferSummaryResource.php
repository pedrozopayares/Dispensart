<?php

namespace App\Http\Resources;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Traslado en el listado (transfers "Consulta de traslados"): estado, bodegas, creador y fecha.
 *
 * @mixin Transfer
 */
final class TransferSummaryResource extends JsonResource
{
    /**
     * @return array{id: int, status: TransferStatus, origin_warehouse: WarehouseResource, destination_warehouse: WarehouseResource, created_by: array{id: int, name: string}, created_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'origin_warehouse' => new WarehouseResource($this->originWarehouse),
            'destination_warehouse' => new WarehouseResource($this->destinationWarehouse),
            'created_by' => ['id' => $this->creator->id, 'name' => $this->creator->name],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
