<?php

namespace App\Http\Controllers\Transfers;

use App\Actions\Transfers\ResolveDiscrepancy;
use App\Http\Requests\Transfers\ResolveDiscrepancyRequest;
use App\Http\Resources\TransferDiscrepancyResource;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\User;

final class TransferDiscrepancyController
{
    /**
     * Resuelve una discrepancia pendiente: devolución al origen (ajuste) o pérdida declarada (transfers.approve).
     */
    public function resolve(
        ResolveDiscrepancyRequest $request,
        Transfer $transfer,
        TransferDiscrepancy $discrepancy,
        ResolveDiscrepancy $action,
    ): TransferDiscrepancyResource {
        /** @var User $user */
        $user = $request->user();

        return new TransferDiscrepancyResource($action->handle($user, $transfer, $discrepancy, $request->resolution()));
    }
}
