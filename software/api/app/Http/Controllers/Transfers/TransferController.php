<?php

namespace App\Http\Controllers\Transfers;

use App\Actions\Transfers\CreateTransfer;
use App\Http\Requests\Transfers\ListTransfersRequest;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Http\Resources\TransferSummaryResource;
use App\Models\Transfer;
use App\Models\User;
use App\Queries\TransferQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TransferController
{
    /**
     * Traslados del más reciente al más antiguo, paginados y filtrables (transfers.view).
     */
    public function index(ListTransfersRequest $request, TransferQuery $query): AnonymousResourceCollection
    {
        return TransferSummaryResource::collection($query->list($request->filters(), $request->perPage()));
    }

    /**
     * Crea un traslado en BORRADOR (transfers.create). No mueve stock.
     */
    public function store(StoreTransferRequest $request, CreateTransfer $create): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new TransferResource($create->handle($user, $request->transfer())))->response()->setStatusCode(201);
    }

    /**
     * Detalle con líneas, discrepancias y el actor y la fecha de cada transición (transfers.view).
     */
    public function show(Transfer $transfer, TransferQuery $query): TransferResource
    {
        return new TransferResource($query->detail($transfer->id));
    }
}
