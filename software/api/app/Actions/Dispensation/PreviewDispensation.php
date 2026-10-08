<?php

namespace App\Actions\Dispensation;

use App\Models\Prescription;
use App\Services\Dispensation\DispensationPlan;
use App\Services\Dispensation\DispensationPlanner;
use App\Services\Dispensation\StockCandidates;
use App\Support\BusinessCalendar;

/**
 * Vista previa FEFO (dispensation "Vista previa de asignación FEFO"): la misma regla que la dispensación
 * (DispensationPlanner), sin bloquear filas ni escribir nada. El faltante es dato, no rechazo.
 */
final class PreviewDispensation
{
    public function __construct(
        private readonly DispensationPlanner $planner,
        private readonly StockCandidates $candidates,
    ) {}

    /**
     * @param  array{prescription_id: int, warehouse_id: int, items: list<array{prescription_item_id: int, quantity: int}>}  $data
     */
    public function handle(array $data): DispensationPlan
    {
        $prescription = Prescription::query()->findOrFail($data['prescription_id']);
        $items = $prescription->items()->with('product')->get();
        $productIds = $items->whereIn('id', array_column($data['items'], 'prescription_item_id'))->pluck('product_id')->all();

        return $this->planner->plan(
            $prescription,
            $items,
            $data['items'],
            $data['warehouse_id'],
            $this->candidates->for($data['warehouse_id'], array_values($productIds), lock: false),
            BusinessCalendar::today(),
        );
    }
}
