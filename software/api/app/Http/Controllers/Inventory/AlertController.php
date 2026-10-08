<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\ListAlertsRequest;
use App\Http\Resources\AlertsResource;
use App\Queries\AlertQuery;

final class AlertController
{
    /**
     * Alertas de inventario (RN-11): lotes con existencia que vencen en 90 días o menos, ya vencidos incluidos,
     * y pares bodega + producto bajo su stock mínimo. Filtro opcional por bodega (inventory.view).
     */
    public function __invoke(ListAlertsRequest $request, AlertQuery $query): AlertsResource
    {
        $warehouseId = $request->warehouseId();

        return new AlertsResource([
            'expiring_lots' => $query->expiringLots($warehouseId),
            'low_stock' => $query->lowStock($warehouseId),
        ]);
    }
}
