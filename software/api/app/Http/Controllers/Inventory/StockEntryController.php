<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ReceiveLot;
use App\Http\Requests\Inventory\StoreStockEntryRequest;
use App\Http\Resources\KardexMovementResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class StockEntryController
{
    /**
     * Registra el ingreso de un lote (lo crea si no existe) y devuelve el movimiento `entrada` creado (solo inventory.adjust).
     */
    public function __invoke(StoreStockEntryRequest $request, ReceiveLot $receive): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new KardexMovementResource($receive->handle($user, $request->entry())))
            ->response()
            ->setStatusCode(201);
    }
}
