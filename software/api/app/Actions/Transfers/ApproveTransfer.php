<?php

namespace App\Actions\Transfers;

use App\Enums\AuditAction;
use App\Enums\TransferAction;
use App\Exceptions\SegregationOfDutiesViolation;
use App\Models\Transfer;
use App\Models\User;
use App\Queries\TransferQuery;
use App\Services\Audit\AuditTrail;
use App\Services\Transfers\TransferLocker;
use App\Services\Transfers\TransferTransitions;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar: SOLICITADO → APROBADO (RN-08, design D8). Segregación antes que estado: quien creó (y por tanto
 * solicitó) el traslado recibe 403 segregation_of_duties aunque sea regente, también sobre un BORRADOR. La base
 * lo repite en transfers_approver_differs. Fila transfer.approved en la misma transacción; sin stock.
 */
final class ApproveTransfer
{
    public function __construct(
        private readonly TransferLocker $locker,
        private readonly AuditTrail $audit,
        private readonly TransferQuery $query,
    ) {}

    /**
     * @throws SegregationOfDutiesViolation
     */
    public function handle(User $actor, Transfer $bound): Transfer
    {
        DB::transaction(function () use ($actor, $bound): void {
            $transfer = $this->locker->lock($bound->id);

            if ($transfer->created_by === $actor->id) {
                throw new SegregationOfDutiesViolation;
            }

            $transfer->forceFill([
                'status' => TransferTransitions::target(TransferAction::Approve, $transfer->status),
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->record($actor->id, AuditAction::TransferApproved, $transfer->id);
        });

        return $this->query->detail($bound->id);
    }
}
