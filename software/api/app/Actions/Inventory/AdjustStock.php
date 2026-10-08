<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Exceptions\LotExpired;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\User;
use App\Services\Inventory\StockChange;
use App\Services\Inventory\StockLedger;
use App\Support\BusinessCalendar;

/**
 * Ajuste de inventario con motivo (inventory "Ajuste de inventario"). Usuario, fecha, tipo y saldo los fija
 * el servidor. Un ingreso a un lote vencido se rechaza; una baja se permite (RN-01).
 */
final class AdjustStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  array{warehouse_id: int, lot_id: int, quantity: int, reason: string}  $data
     *
     * @throws LotExpired
     */
    public function handle(User $user, array $data): KardexMovement
    {
        $lot = Lot::query()->findOrFail($data['lot_id']);

        if ($data['quantity'] > 0 && $lot->isExpiredOn(BusinessCalendar::today())) {
            throw new LotExpired;
        }

        [$movement] = $this->ledger->apply([
            new StockChange(
                warehouseId: $data['warehouse_id'],
                lotId: $lot->id,
                delta: $data['quantity'],
                type: MovementType::Adjustment,
                userId: $user->id,
                reason: $data['reason'],
            ),
        ]);

        return $movement->load(['warehouse', 'product', 'lot', 'user']);
    }
}
