<?php

namespace App\Models;

use App\Enums\PatientAccessAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila de la bitácora de acceso a pacientes (RN-10). Solo inserción: la base rechaza UPDATE, DELETE y
 * TRUNCATE. Sin datos personales: solo ids, ruta por patrón y correlation_id.
 *
 * @property int $id
 * @property int $user_id
 * @property int $patient_id
 * @property PatientAccessAction $action
 * @property string $route
 * @property string|null $correlation_id
 * @property CarbonImmutable $created_at
 */
class PatientAccessLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PatientAccessAction::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
