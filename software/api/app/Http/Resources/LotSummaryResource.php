<?php

namespace App\Http\Resources;

use App\Models\Lot;
use App\Support\BusinessCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lote embebido en existencias y movimientos, con vencimiento calculado en cada respuesta (RN-01).
 *
 * @mixin Lot
 */
final class LotSummaryResource extends JsonResource
{
    /**
     * @return array{id: int, lot_code: string, expires_on: string, is_expired: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_code' => $this->lot_code,
            'expires_on' => $this->expires_on->toDateString(),
            'is_expired' => $this->isExpiredOn(BusinessCalendar::today()),
        ];
    }
}
