<?php

namespace App\Services\Dispensation;

use App\Models\PrescriptionItem;

/**
 * Plan de un ítem pedido: cantidad, asignación FEFO y si exige coautorización (RN-05).
 */
final readonly class ItemPlan
{
    public function __construct(
        public PrescriptionItem $item,
        public int $requested,
        public Allocation $allocation,
    ) {}

    public function requiresAuthorization(): bool
    {
        return $this->item->product->is_controlled;
    }
}
