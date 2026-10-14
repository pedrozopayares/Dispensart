<?php

namespace App\Actions\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Exceptions\LotExpired;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Services\Inventory\StockChange;
use App\Services\Inventory\StockLedger;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso de lote desde POST /api/stock-entries (inventory "Ingreso de lote", S18 D2). En una transacción: lote
 * vencido → resolver el lote (alta si falta, reutilizado si el vencimiento coincide) → un movimiento `entrada` por
 * el libro → fila `stock.received` con solo ids (nunca el motivo ni el código). El alta del lote usa ON CONFLICT
 * DO NOTHING: dos ingresos simultáneos del mismo lote nuevo comparten un solo lote (D3).
 */
final class ReceiveLot
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @param  array{warehouse_id: int, product_id: int, lot_code: string, expires_on: string, quantity: int, reason: string}  $data
     *
     * @throws LotExpired
     * @throws ValidationException si el lote existe con otro vencimiento
     */
    public function handle(User $user, array $data): KardexMovement
    {
        $expiresOn = CarbonImmutable::parse($data['expires_on'])->toDateString();
        if ($expiresOn <= BusinessCalendar::today()->toDateString()) {
            throw new LotExpired;
        }

        return DB::transaction(function () use ($user, $data, $expiresOn): KardexMovement {
            $lot = $this->resolveLot($data['product_id'], $data['lot_code'], $expiresOn);

            [$movement] = $this->ledger->apply([
                new StockChange(
                    warehouseId: $data['warehouse_id'],
                    lotId: $lot->id,
                    delta: $data['quantity'],
                    type: MovementType::Inbound,
                    userId: $user->id,
                    reason: $data['reason'],
                ),
            ]);

            $this->audit->record($user->id, AuditAction::StockReceived, $movement->id, [
                'warehouse_id' => $movement->warehouse_id,
                'lot_id' => $movement->lot_id,
            ]);

            return $movement->load(['warehouse', 'product', 'lot', 'user']);
        });
    }

    /**
     * Alta sin choque: una inserción simultánea del mismo producto + código espera a la otra y no inserta nada;
     * la lectura siguiente ve el lote ya confirmado.
     *
     * @throws ValidationException
     */
    private function resolveLot(int $productId, string $lotCode, string $expiresOn): Lot
    {
        DB::table('lots')->insertOrIgnore([
            'product_id' => $productId,
            'lot_code' => $lotCode,
            'expires_on' => $expiresOn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lot = Lot::query()->where('product_id', $productId)->where('lot_code', $lotCode)->sole();
        if ($lot->expires_on->toDateString() !== $expiresOn) {
            throw ValidationException::withMessages(['expires_on' => [__('validation.lot_expiry_mismatch')]]);
        }

        return $lot;
    }
}
