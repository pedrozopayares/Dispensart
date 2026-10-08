<?php

namespace App\Services\Dispensation;

use App\Enums\PrescriptionStatus;
use App\Exceptions\ExceedsPrescription;
use App\Exceptions\PrescriptionExhausted;
use App\Exceptions\PrescriptionExpired;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Stock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Regla única de prescripción + FEFO (design D1, D4 pasos 8, 9 y 11), compartida por la vista previa y la
 * dispensación: estado (agotada antes que vencida) → cantidad ≤ pendiente por ítem → FEFO de cada ítem.
 * Pura sobre filas ya cargadas: quien llama decide si están bloqueadas.
 */
final class DispensationPlanner
{
    public function __construct(private readonly FefoAllocator $allocator) {}

    /**
     * Vista previa: reglas de prescripción y FEFO sobre las filas dadas.
     *
     * @param  Collection<int, PrescriptionItem>  $items  todos los ítems de la prescripción, con `product`
     * @param  list<array{prescription_item_id: int, quantity: int}>  $requested  en el orden de la petición
     * @param  Collection<int, Stock>  $candidates  existencias de la bodega de los productos pedidos, con `lot`
     *
     * @throws PrescriptionExhausted|PrescriptionExpired|ExceedsPrescription
     */
    public function plan(
        Prescription $prescription,
        Collection $items,
        array $requested,
        int $warehouseId,
        Collection $candidates,
        CarbonImmutable $today,
    ): DispensationPlan {
        $this->check($prescription, $items, $requested, $today);

        return $this->allocate($prescription, $items, $requested, $warehouseId, $candidates, $today);
    }

    /**
     * Pasos 8 y 9: estado (agotada antes que vencida) y cantidad ≤ pendiente de cada ítem.
     *
     * @param  Collection<int, PrescriptionItem>  $items
     * @param  list<array{prescription_item_id: int, quantity: int}>  $requested
     *
     * @throws PrescriptionExhausted|PrescriptionExpired|ExceedsPrescription
     */
    public function check(Prescription $prescription, Collection $items, array $requested, CarbonImmutable $today): void
    {
        $prescription->setRelation('items', $items);

        match ($prescription->statusOn($today)) {
            PrescriptionStatus::Exhausted => throw new PrescriptionExhausted,
            PrescriptionStatus::Expired => throw new PrescriptionExpired,
            PrescriptionStatus::Active => null,
        };

        $byId = $items->keyBy('id');
        foreach ($requested as $line) {
            /** @var PrescriptionItem $item */
            $item = $byId[$line['prescription_item_id']];
            if ($line['quantity'] > $item->pendingQuantity()) {
                throw new ExceedsPrescription;
            }
        }
    }

    /**
     * Paso 11: FEFO de cada ítem pedido sobre las existencias candidatas (bloqueadas o no, según quien llama).
     *
     * @param  Collection<int, PrescriptionItem>  $items
     * @param  list<array{prescription_item_id: int, quantity: int}>  $requested
     * @param  Collection<int, Stock>  $candidates
     */
    public function allocate(
        Prescription $prescription,
        Collection $items,
        array $requested,
        int $warehouseId,
        Collection $candidates,
        CarbonImmutable $today,
    ): DispensationPlan {
        $byId = $items->keyBy('id');
        $byProduct = $candidates->groupBy('product_id');
        $plans = [];
        foreach ($requested as $line) {
            /** @var PrescriptionItem $item */
            $item = $byId[$line['prescription_item_id']];
            $plans[] = new ItemPlan(
                $item,
                $line['quantity'],
                $this->allocator->allocate($byProduct->get($item->product_id, collect()), $line['quantity'], $today),
            );
        }

        return new DispensationPlan($prescription, $warehouseId, $plans);
    }
}
