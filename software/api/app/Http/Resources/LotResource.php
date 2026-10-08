<?php

namespace App\Http\Resources;

use App\Models\Lot;
use App\Support\BusinessCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lote con estado de vencimiento calculado en cada respuesta (RN-01, catalog "Estado de vencimiento").
 *
 * @mixin Lot
 */
final class LotResource extends JsonResource
{
    /**
     * @return array{id: int, product_id: int, lot_code: string, expires_on: string, is_expired: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'lot_code' => $this->lot_code,
            'expires_on' => $this->expires_on->toDateString(),
            'is_expired' => $this->isExpiredOn(BusinessCalendar::today()),
        ];
    }
}
