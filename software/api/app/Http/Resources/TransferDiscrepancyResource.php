<?php

namespace App\Http\Resources;

use App\Enums\DiscrepancyResolution;
use App\Enums\DiscrepancyStatus;
use App\Models\TransferDiscrepancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Discrepancia de recepción con su resolución (transfers "Resolución de discrepancias"). Del resolutor solo id y
 * nombre.
 *
 * @mixin TransferDiscrepancy
 */
final class TransferDiscrepancyResource extends JsonResource
{
    /**
     * @return array{id: int, line_id: int, lot_id: int, shortage: int, status: DiscrepancyStatus, resolution: DiscrepancyResolution|null, resolution_reason: string|null, resolved_by: array{id: int, name: string}|null, resolved_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_id' => $this->transfer_line_id,
            'lot_id' => $this->line->lot_id,
            'shortage' => $this->shortage,
            'status' => $this->status,
            'resolution' => $this->resolution,
            'resolution_reason' => $this->resolution_reason,
            'resolved_by' => $this->resolver === null ? null : ['id' => $this->resolver->id, 'name' => $this->resolver->name],
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
