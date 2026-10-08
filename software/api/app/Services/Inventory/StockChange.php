<?php

namespace App\Services\Inventory;

use App\Enums\MovementType;
use InvalidArgumentException;

/**
 * Un cambio de existencia pedido al libro (design D2): bodega + lote, variación con signo y su movimiento.
 * El producto se deriva del lote. Un signo contrario al tipo es un defecto del llamador, no un flujo.
 */
final readonly class StockChange
{
    public function __construct(
        public int $warehouseId,
        public int $lotId,
        public int $delta,
        public MovementType $type,
        public ?int $userId = null,
        public ?string $reason = null,
    ) {
        if (! $type->allowsQuantity($delta)) {
            throw new InvalidArgumentException("Variación {$delta} no admitida para el tipo {$type->value}.");
        }
    }

    /**
     * Identidad de la existencia afectada (bodega + lote; el lote fija el producto).
     */
    public function stockKey(): string
    {
        return $this->warehouseId.':'.$this->lotId;
    }
}
