<?php

namespace App\Actions\Transfers;

use App\Enums\MovementType;
use App\Enums\TransferAction;
use App\Exceptions\InsufficientStock;
use App\Exceptions\LotExpired;
use App\Models\Transfer;
use App\Models\TransferLine;
use App\Models\User;
use App\Queries\TransferQuery;
use App\Services\Inventory\StockChange;
use App\Services\Inventory\StockLedger;
use App\Services\Transfers\TransferLocker;
use App\Services\Transfers\TransferTransitions;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Despachar: APROBADO → EN_TRANSITO, todo o nada (RN-01, RN-03, RN-06; design D7). Bajo el bloqueo del traslado:
 * estado → vencimiento de todas las líneas (antes que el stock) → una sola llamada al libro con un
 * salida_traslado por línea en origen (el libro bloquea en la clave global y valida todos los saldos antes de
 * escribir) → enlace de cada línea a su movimiento → estado y despachador.
 */
final class DispatchTransfer
{
    public function __construct(
        private readonly TransferLocker $locker,
        private readonly StockLedger $ledger,
        private readonly TransferQuery $query,
    ) {}

    /**
     * @throws LotExpired
     * @throws InsufficientStock
     */
    public function handle(User $actor, Transfer $bound): Transfer
    {
        DB::transaction(function () use ($actor, $bound): void {
            $transfer = $this->locker->lock($bound->id);
            $target = TransferTransitions::target(TransferAction::Dispatch, $transfer->status);

            $lines = $transfer->lines()->with('lot')->get()->values();
            $today = BusinessCalendar::today();
            if ($lines->contains(fn (TransferLine $line): bool => $line->lot->isExpiredOn($today))) {
                throw new LotExpired;
            }

            $changes = [];
            foreach ($lines as $line) {
                $changes[] = new StockChange(
                    $transfer->origin_warehouse_id, $line->lot_id, -$line->quantity, MovementType::TransferOutbound,
                    $actor->id, "Traslado #{$transfer->id}",
                );
            }

            // apply() devuelve los movimientos en el orden de los cambios; el lote lo confirma.
            foreach ($this->ledger->apply($changes) as $index => $movement) {
                $line = $lines[$index];
                if ($movement->lot_id !== $line->lot_id) {
                    throw new LogicException('Movimiento de despacho fuera de orden.');
                }
                $line->forceFill(['dispatch_movement_id' => $movement->id])->save();
            }

            $transfer->forceFill([
                'status' => $target,
                'dispatched_by' => $actor->id,
                'dispatched_at' => now(),
            ])->save();
        });

        return $this->query->detail($bound->id);
    }
}
