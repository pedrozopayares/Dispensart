<?php

namespace App\Models;

use App\Enums\PrescriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prescripción de un médico a un paciente (RN-04). El estado se calcula en cada lectura, nunca se guarda.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $prescriber_id
 * @property CarbonImmutable $valid_until
 * @property CarbonImmutable $created_at
 */
#[Fillable(['patient_id', 'prescriber_id', 'valid_until'])]
class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_until' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescriber_id');
    }

    /**
     * @return HasMany<PrescriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('id');
    }

    /**
     * Estado en la fecha de negocio dada, sobre los ítems cargados (los bloqueados, en la dispensación):
     * `agotada` si ningún ítem tiene pendiente; si no, `vencida` si valid_until es anterior a hoy
     * (inclusivo); si no, `vigente`. Gemelo de Lot::isExpiredOn(): compara fechas calendario.
     */
    public function statusOn(CarbonImmutable $today): PrescriptionStatus
    {
        if ($this->items->every(fn (PrescriptionItem $item): bool => $item->pendingQuantity() === 0)) {
            return PrescriptionStatus::Exhausted;
        }

        return $this->valid_until->toDateString() < $today->toDateString()
            ? PrescriptionStatus::Expired
            : PrescriptionStatus::Active;
    }
}
