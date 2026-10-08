<?php

namespace App\Models;

use App\Enums\DiscrepancyResolution;
use App\Enums\DiscrepancyStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Faltante de una línea en una recepción parcial (RN-07). Nace pendiente en la transacción de la recepción y se
 * resuelve una sola vez, bajo el bloqueo de su fila (design D4, D10).
 *
 * @property int $id
 * @property int $transfer_id
 * @property int $transfer_line_id
 * @property int $shortage
 * @property DiscrepancyStatus $status
 * @property DiscrepancyResolution|null $resolution
 * @property string|null $resolution_reason
 * @property int|null $resolved_by
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $adjustment_movement_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TransferDiscrepancy extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shortage' => 'integer',
            'status' => DiscrepancyStatus::class,
            'resolution' => DiscrepancyResolution::class,
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return BelongsTo<TransferLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(TransferLine::class, 'transfer_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
