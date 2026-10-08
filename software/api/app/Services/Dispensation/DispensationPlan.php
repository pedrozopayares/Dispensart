<?php

namespace App\Services\Dispensation;

use App\Models\Prescription;

/**
 * Plan completo de una dispensación (vista previa o ejecución), ítems en el orden de la petición.
 */
final readonly class DispensationPlan
{
    /**
     * @param  list<ItemPlan>  $items
     */
    public function __construct(
        public Prescription $prescription,
        public int $warehouseId,
        public array $items,
    ) {}

    public function fulfillable(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->allocation->isFulfilled()) {
                return false;
            }
        }

        return true;
    }

    public function requiresAuthorization(): bool
    {
        foreach ($this->items as $item) {
            if ($item->requiresAuthorization()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Faltantes de todos los ítems que no alcanzan (dispensation "Stock insuficiente, todo o nada").
     *
     * @return list<array{prescription_item_id: int, product_id: int, requested: int, available: int}>
     */
    public function shortages(): array
    {
        $shortages = [];
        foreach ($this->items as $item) {
            if (! $item->allocation->isFulfilled()) {
                $shortages[] = [
                    'prescription_item_id' => $item->item->id,
                    'product_id' => $item->item->product_id,
                    'requested' => $item->requested,
                    'available' => $item->allocation->available,
                ];
            }
        }

        return $shortages;
    }
}
