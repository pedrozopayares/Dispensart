<?php

namespace App\Http\Resources;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prescripción con estado calculado en esta lectura, médico (solo id y nombre) e ítems con saldos (RN-04).
 *
 * @mixin Prescription
 */
final class PrescriptionResource extends JsonResource
{
    private ?CarbonImmutable $today = null;

    public function onDate(CarbonImmutable $today): self
    {
        $this->today = $today;

        return $this;
    }

    /**
     * @return array{id: int, patient_id: int, status: string, valid_until: string, created_at: string, prescriber: array{id: int, name: string}, items: list<array{id: int, product: array{id: int, code: string, name: string, is_controlled: bool}, prescribed_quantity: int, dispensed_quantity: int, pending_quantity: int}>}
     */
    public function toArray(Request $request): array
    {
        /** @var Prescription $prescription */
        $prescription = $this->resource;

        return [
            'id' => $prescription->id,
            'patient_id' => $prescription->patient_id,
            'status' => $prescription->statusOn($this->today ?? BusinessCalendar::today())->value,
            'valid_until' => $prescription->valid_until->toDateString(),
            'created_at' => $prescription->created_at->toIso8601String(),
            'prescriber' => ['id' => $prescription->prescriber->id, 'name' => $prescription->prescriber->name],
            'items' => $prescription->items->map(fn (PrescriptionItem $item): array => [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'code' => $item->product->code,
                    'name' => $item->product->name,
                    'is_controlled' => $item->product->is_controlled,
                ],
                'prescribed_quantity' => $item->prescribed_quantity,
                'dispensed_quantity' => $item->dispensed_quantity,
                'pending_quantity' => $item->pendingQuantity(),
            ])->values()->all(),
        ];
    }
}
