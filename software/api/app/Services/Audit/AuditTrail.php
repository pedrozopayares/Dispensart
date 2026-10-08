<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora de operaciones sensibles (audit-trail, RN-05, RN-10). Escribe en la conexión y transacción en
 * curso: las operaciones exitosas quedan en su transacción; el fallo de autorización se escribe fuera de toda
 * transacción y persiste aunque la petición se rechace. El detalle solo admite ids (la base lo exige).
 */
final class AuditTrail
{
    /**
     * @param  array<string, int>  $details
     */
    public function record(int $actorId, AuditAction $action, int $subjectId, array $details = []): void
    {
        $correlationId = Context::get(AssignCorrelationId::CONTEXT_KEY);

        DB::table('audit_events')->insert([
            'actor_id' => $actorId,
            'action' => $action->value,
            'subject_type' => $action->subjectType(),
            'subject_id' => $subjectId,
            'details' => json_encode((object) $details),
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
        ]);
    }
}
