<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustStockManually;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Http\Resources\KardexMovementResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class StockAdjustmentController
{
    /**
     * Ajusta una existencia con motivo y devuelve el movimiento `ajuste` creado (solo inventory.adjust).
     */
    public function __invoke(StoreStockAdjustmentRequest $request, AdjustStockManually $adjust): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new KardexMovementResource($adjust->handle($user, $request->adjustment())))
            ->response()
            ->setStatusCode(201);
    }
}
