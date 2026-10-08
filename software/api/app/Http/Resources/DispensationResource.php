<?php

namespace App\Http\Resources;

use App\Models\Dispensation;
use App\Models\DispensationLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dispensación creada (dispensation "Dispensación contra prescripción vigente"). Solo ids, lotes y cantidades:
 * ningún dato del paciente, porque este cuerpo se guarda como respuesta idempotente (design D5).
 *
 * @mixin Dispensation
 */
final class DispensationResource extends JsonResource
{
    /**
     * @return array{id: int, prescription_id: int, patient_id: int, warehouse_id: int, dispensed_by: int, authorized_by: int|null, created_at: string, lines: list<array{id: int, prescription_item_id: int, product_id: int, lot_id: int, lot_code: string, expires_on: string, quantity: int, kardex_movement_id: int}>}
     */
    public function toArray(Request $request): array
    {
        /** @var Dispensation $dispensation */
        $dispensation = $this->resource;

        return [
            'id' => $dispensation->id,
            'prescription_id' => $dispensation->prescription_id,
            'patient_id' => $dispensation->patient_id,
            'warehouse_id' => $dispensation->warehouse_id,
            'dispensed_by' => $dispensation->dispensed_by,
            'authorized_by' => $dispensation->authorized_by,
            'created_at' => $dispensation->created_at->toIso8601String(),
            'lines' => $dispensation->lines->map(fn (DispensationLine $line): array => [
                'id' => $line->id,
                'prescription_item_id' => $line->prescription_item_id,
                'product_id' => $line->product_id,
                'lot_id' => $line->lot_id,
                'lot_code' => $line->lot->lot_code,
                'expires_on' => $line->lot->expires_on->toDateString(),
                'quantity' => $line->quantity,
                'kardex_movement_id' => $line->kardex_movement_id,
            ])->values()->all(),
        ];
    }
}
