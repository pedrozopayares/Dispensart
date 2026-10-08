<?php

namespace App\Actions\Transfers;

use App\Enums\TransferAction;
use App\Models\Transfer;
use App\Models\User;
use App\Queries\TransferQuery;
use App\Services\Transfers\TransferLocker;
use App\Services\Transfers\TransferTransitions;
use Illuminate\Support\Facades\DB;

/**
 * Solicitar: BORRADOR → SOLICITADO por su creador (la Policy ya exigió creador; la base lo repite en
 * transfers_requester_is_creator). Sin stock ni bitácora.
 */
final class RequestTransfer
{
    public function __construct(private readonly TransferLocker $locker, private readonly TransferQuery $query) {}

    public function handle(User $actor, Transfer $bound): Transfer
    {
        DB::transaction(function () use ($actor, $bound): void {
            $transfer = $this->locker->lock($bound->id);

            $transfer->forceFill([
                'status' => TransferTransitions::target(TransferAction::Request, $transfer->status),
                'requested_by' => $actor->id,
                'requested_at' => now(),
            ])->save();
        });

        return $this->query->detail($bound->id);
    }
}
