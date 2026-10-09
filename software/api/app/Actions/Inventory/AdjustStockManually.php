<?php

namespace App\Actions\Inventory;

use App\Enums\AuditAction;
use App\Exceptions\LotExpired;
use App\Models\KardexMovement;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Ajuste manual de inventario desde POST /api/stock-adjustments (S10 D2): el ajuste y su fila `stock.adjusted`
 * (actor = quien ajusta, objeto = el movimiento `ajuste`) van en una sola transacción. Si la fila no se escribe,
 * existencia y kardex quedan como estaban; un ajuste rechazado no llega a la bitácora. La fila lleva solo ids
 * (bodega y lote), nunca el motivo. La resolución de discrepancias llama a AdjustStock directo y no escribe esta fila.
 */
final class AdjustStockManually
{
    public function __construct(
        private readonly AdjustStock $adjust,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @param  array{warehouse_id: int, lot_id: int, quantity: int, reason: string}  $data
     *
     * @throws LotExpired
     */
    public function handle(User $user, array $data): KardexMovement
    {
        return DB::transaction(function () use ($user, $data): KardexMovement {
            $movement = $this->adjust->handle($user, $data);

            $this->audit->record($user->id, AuditAction::StockAdjusted, $movement->id, [
                'warehouse_id' => $movement->warehouse_id,
                'lot_id' => $movement->lot_id,
            ]);

            return $movement;
        });
    }
}
