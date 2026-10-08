<?php

namespace App\Services\Audit;

use App\Enums\PatientAccessAction;
use App\Http\Middleware\AssignCorrelationId;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora de acceso a pacientes (audit-trail "Bitácora de acceso a pacientes", design D8): una fila por
 * paciente devuelto, en una sola sentencia, antes de serializar la respuesta. Si la escritura falla, la
 * excepción sube y la petición responde 500 sin datos: falla cerrado. Sin datos personales: solo ids.
 */
final class PatientAccessRecorder
{
    /**
     * @param  list<int>  $patientIds
     */
    public function record(User $user, array $patientIds, PatientAccessAction $action, string $routePattern): void
    {
        if ($patientIds === []) {
            return;
        }

        $correlationId = Context::get(AssignCorrelationId::CONTEXT_KEY);
        $correlationId = is_string($correlationId) ? $correlationId : null;

        DB::table('patient_access_logs')->insert(array_map(fn (int $patientId): array => [
            'user_id' => $user->id,
            'patient_id' => $patientId,
            'action' => $action->value,
            'route' => $routePattern,
            'correlation_id' => $correlationId,
        ], $patientIds));
    }
}
