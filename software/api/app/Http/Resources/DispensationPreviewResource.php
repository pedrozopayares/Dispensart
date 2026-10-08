<?php

namespace App\Http\Resources;

use App\Services\Dispensation\AllocationLine;
use App\Services\Dispensation\DispensationPlan;
use App\Services\Dispensation\ItemPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vista previa FEFO (dispensation "Vista previa de asignación FEFO"): por ítem, lotes que se tomarían en
 * orden, disponible, faltante, vencido excluido y si exige autorización.
 *
 * @mixin DispensationPlan
 */
final class DispensationPreviewResource extends JsonResource
{
    /**
     * @return array{prescription_id: int, warehouse_id: int, requires_authorization: bool, fulfillable: bool, items: list<array{prescription_item_id: int, product_id: int, requested: int, available: int, shortage: int, expired_excluded_quantity: int, requires_authorization: bool, allocations: list<array{lot_id: int, lot_code: string, expires_on: string, quantity: int}>}>}
     */
    public function toArray(Request $request): array
    {
        /** @var DispensationPlan $plan */
        $plan = $this->resource;

        return [
            'prescription_id' => $plan->prescription->id,
            'warehouse_id' => $plan->warehouseId,
            'requires_authorization' => $plan->requiresAuthorization(),
            'fulfillable' => $plan->fulfillable(),
            'items' => collect($plan->items)->map(fn (ItemPlan $item): array => [
                'prescription_item_id' => $item->item->id,
                'product_id' => $item->item->product_id,
                'requested' => $item->requested,
                'available' => $item->allocation->available,
                'shortage' => $item->allocation->shortage,
                'expired_excluded_quantity' => $item->allocation->expiredExcluded,
                'requires_authorization' => $item->requiresAuthorization(),
                'allocations' => collect($item->allocation->lines)->map(fn (AllocationLine $line): array => [
                    'lot_id' => $line->lotId,
                    'lot_code' => $line->lotCode,
                    'expires_on' => $line->expiresOn->toDateString(),
                    'quantity' => $line->quantity,
                ])->all(),
            ])->all(),
        ];
    }
}
