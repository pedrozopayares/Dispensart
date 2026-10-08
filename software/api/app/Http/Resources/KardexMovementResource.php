<?php

namespace App\Http\Resources;

use App\Enums\MovementType;
use App\Models\KardexMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Movimiento del kardex (kardex "Consulta del kardex"). `user` es null en los movimientos del sistema
 * (siembra). Del usuario solo se expone id y nombre.
 *
 * @mixin KardexMovement
 */
final class KardexMovementResource extends JsonResource
{
    /**
     * @return array{id: int, type: MovementType, quantity: int, balance_after: int, reason: string|null, created_at: string, warehouse: WarehouseResource, product: ProductSummaryResource, lot: LotSummaryResource, user: array{id: int, name: string}|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'reason' => $this->reason,
            'created_at' => $this->created_at->toIso8601String(),
            'warehouse' => new WarehouseResource($this->warehouse),
            'product' => new ProductSummaryResource($this->product),
            'lot' => new LotSummaryResource($this->lot),
            'user' => $this->user === null ? null : ['id' => $this->user->id, 'name' => $this->user->name],
        ];
    }
}
