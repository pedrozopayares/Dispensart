<?php

namespace App\Actions\Transfers;

use App\Actions\Inventory\AdjustStock;
use App\Enums\AuditAction;
use App\Enums\DiscrepancyResolution;
use App\Enums\DiscrepancyStatus;
use App\Exceptions\DiscrepancyAlreadyResolved;
use App\Exceptions\LotExpired;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Resolver una discrepancia pendiente (design D10). Bloquea solo la discrepancia, no el traslado (design D4): la
 * resolución no cambia el estado del traslado. returned_to_origin suma el faltante al lote en origen por el
 * ajuste de S2 (regla de lote vencido en un solo lugar → 422 lot_expired); written_off no mueve stock. Fila
 * transfer.discrepancy_resolved con solo ids.
 */
final class ResolveDiscrepancy
{
    private const KARDEX_REASON_LIMIT = 500;

    public function __construct(private readonly AdjustStock $adjust, private readonly AuditTrail $audit) {}

    /**
     * @param  array{resolution: DiscrepancyResolution, reason: string}  $data
     *
     * @throws DiscrepancyAlreadyResolved
     * @throws LotExpired
     */
    public function handle(User $actor, Transfer $transfer, TransferDiscrepancy $bound, array $data): TransferDiscrepancy
    {
        $discrepancyId = DB::transaction(function () use ($actor, $transfer, $bound, $data): int {
            $discrepancy = TransferDiscrepancy::query()
                ->whereKey($bound->id)
                ->where('transfer_id', $transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($discrepancy->status === DiscrepancyStatus::Resolved) {
                throw new DiscrepancyAlreadyResolved;
            }

            $movementId = null;
            if ($data['resolution'] === DiscrepancyResolution::ReturnedToOrigin) {
                $reason = "Traslado #{$transfer->id}, discrepancia #{$discrepancy->id}: {$data['reason']}";
                $movementId = $this->adjust->handle($actor, [
                    'warehouse_id' => $transfer->origin_warehouse_id,
                    'lot_id' => $discrepancy->line()->firstOrFail()->lot_id,
                    'quantity' => $discrepancy->shortage,
                    'reason' => mb_substr($reason, 0, self::KARDEX_REASON_LIMIT),
                ])->id;
            }

            $discrepancy->forceFill([
                'status' => DiscrepancyStatus::Resolved,
                'resolution' => $data['resolution'],
                'resolution_reason' => $data['reason'],
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'adjustment_movement_id' => $movementId,
            ])->save();

            $this->audit->record(
                $actor->id, AuditAction::TransferDiscrepancyResolved, $transfer->id, ['discrepancy_id' => $discrepancy->id],
            );

            return $discrepancy->id;
        });

        return TransferDiscrepancy::query()->with(['line', 'resolver:id,name'])->findOrFail($discrepancyId);
    }
}
