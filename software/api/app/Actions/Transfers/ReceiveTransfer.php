<?php

namespace App\Actions\Transfers;

use App\Enums\DiscrepancyStatus;
use App\Enums\MovementType;
use App\Enums\TransferAction;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Queries\TransferQuery;
use App\Services\Inventory\StockChange;
use App\Services\Inventory\StockLedger;
use App\Services\Transfers\ReceiptCalculator;
use App\Services\Transfers\TransferLocker;
use App\Services\Transfers\TransferTransitions;
use Illuminate\Support\Facades\DB;

/**
 * Recibir: EN_TRANSITO → RECIBIDO | RECIBIDO_PARCIAL (RN-06, RN-07; design D6). Bajo el bloqueo del traslado:
 * estado → cálculo puro → una sola llamada al libro con un entrada_traslado en destino por línea con recibido > 0
 * (el libro crea la existencia si falta) → cantidades y enlaces por línea → una discrepancia pendiente por línea
 * con faltante → estado y receptor.
 */
final class ReceiveTransfer
{
    public function __construct(
        private readonly TransferLocker $locker,
        private readonly StockLedger $ledger,
        private readonly ReceiptCalculator $calculator,
        private readonly TransferQuery $query,
    ) {}

    /**
     * @param  array<int, int>  $received  id de línea → cantidad recibida
     */
    public function handle(User $actor, Transfer $bound, array $received): Transfer
    {
        DB::transaction(function () use ($actor, $bound, $received): void {
            $transfer = $this->locker->lock($bound->id);
            TransferTransitions::assertAllowed(TransferAction::Receive, $transfer->status);

            $lines = $transfer->lines()->get()->keyBy('id');
            $outcome = $this->calculator->compute($lines->map(fn ($line): int => $line->quantity)->all(), $received);
            $target = TransferTransitions::target(TransferAction::Receive, $transfer->status, $outcome->status);

            $changes = [];
            $receiving = [];
            foreach ($lines as $line) {
                if ($received[$line->id] > 0) {
                    $changes[] = new StockChange(
                        $transfer->destination_warehouse_id, $line->lot_id, $received[$line->id],
                        MovementType::TransferInbound, $actor->id, "Traslado #{$transfer->id}",
                    );
                    $receiving[] = $line->id;
                }
            }
            $movements = [];
            foreach ($this->ledger->apply($changes) as $index => $movement) {
                $movements[$receiving[$index]] = $movement->id;
            }

            foreach ($lines as $line) {
                $line->forceFill([
                    'received_quantity' => $received[$line->id],
                    'receipt_movement_id' => $movements[$line->id] ?? null,
                ])->save();
            }

            foreach ($outcome->shortages as $lineId => $shortage) {
                (new TransferDiscrepancy)->forceFill([
                    'transfer_id' => $transfer->id,
                    'transfer_line_id' => $lineId,
                    'shortage' => $shortage,
                    'status' => DiscrepancyStatus::Pending,
                ])->save();
            }

            $transfer->forceFill([
                'status' => $target,
                'received_by' => $actor->id,
                'received_at' => now(),
            ])->save();
        });

        return $this->query->detail($bound->id);
    }
}
