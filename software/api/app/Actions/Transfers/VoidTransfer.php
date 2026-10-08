<?php

namespace App\Actions\Transfers;

use App\Enums\AuditAction;
use App\Enums\TransferAction;
use App\Models\Transfer;
use App\Models\User;
use App\Queries\TransferQuery;
use App\Services\Audit\AuditTrail;
use App\Services\Transfers\TransferLocker;
use App\Services\Transfers\TransferTransitions;
use Illuminate\Support\Facades\DB;

/**
 * Anular: BORRADOR, SOLICITADO o APROBADO → ANULADO con motivo (RN-07). Sin existencias ni kardex. Fila
 * transfer.voided sin texto libre (el motivo vive en el traslado, nunca en la bitácora).
 */
final class VoidTransfer
{
    public function __construct(
        private readonly TransferLocker $locker,
        private readonly AuditTrail $audit,
        private readonly TransferQuery $query,
    ) {}

    public function handle(User $actor, Transfer $bound, string $reason): Transfer
    {
        DB::transaction(function () use ($actor, $bound, $reason): void {
            $transfer = $this->locker->lock($bound->id);

            $transfer->forceFill([
                'status' => TransferTransitions::target(TransferAction::Void, $transfer->status),
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $this->audit->record($actor->id, AuditAction::TransferVoided, $transfer->id);
        });

        return $this->query->detail($bound->id);
    }
}
