<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dispensación contra una prescripción vigente (RN-04). Solo la escribe DispenseMedication y nunca se
 * actualiza. La fecha la pone la base.
 *
 * @property int $id
 * @property int $prescription_id
 * @property int $patient_id
 * @property int $warehouse_id
 * @property int $dispensed_by
 * @property int|null $authorized_by
 * @property CarbonImmutable $created_at
 */
class Dispensation extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<DispensationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DispensationLine::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
