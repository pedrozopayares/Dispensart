<?php

namespace App\Models;

use App\Enums\DocumentType;
use Carbon\CarbonImmutable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paciente (dato personal de salud, RN-10). Solo por siembra: ningún rol lo da de alta. Nunca se registra
 * en logs: se identifica por id. El auditor lo recibe enmascarado (PatientResource).
 *
 * @property int $id
 * @property DocumentType $document_type
 * @property string $document_number
 * @property string $full_name
 * @property CarbonImmutable|null $birth_date
 * @property string|null $phone
 */
#[Fillable(['document_type', 'document_number', 'full_name', 'birth_date', 'phone'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'birth_date' => 'immutable_date',
        ];
    }

    /**
     * @return HasMany<Prescription, $this>
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }
}
