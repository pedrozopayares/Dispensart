<?php

namespace App\Services\Assistant;

/**
 * `outcome` decidido por el servidor a partir de las llamadas, nunca por el texto del modelo (inventory-assistant
 * «Resultado decidido por el servidor»; design D7 paso 3). Función pura. Precedencia: herramienta fuera del
 * catálogo o límite del ciclo → unknown; alguna `ok` con datos → answered; `ok` todas vacías → no_results;
 * solo `denied` → not_permitted; sin llamadas → out_of_scope; resto → unknown.
 */
final class OutcomeResolver
{
    /**
     * @param  list<ToolCallRecord>  $calls
     */
    public function resolve(array $calls, bool $limitHit): Outcome
    {
        $statuses = array_map(fn (ToolCallRecord $call): ToolCallStatus => $call->status, $calls);

        if ($limitHit || in_array(ToolCallStatus::Rejected, $statuses, true)) {
            return Outcome::Unknown;
        }
        foreach ($calls as $call) {
            if ($call->hasData()) {
                return Outcome::Answered;
            }
        }
        if (in_array(ToolCallStatus::Ok, $statuses, true)) {
            return Outcome::NoResults;
        }
        if ($calls === []) {
            return Outcome::OutOfScope;
        }
        if (array_unique(array_map(fn (ToolCallStatus $s): string => $s->value, $statuses)) === [ToolCallStatus::Denied->value]) {
            return Outcome::NotPermitted;
        }

        return Outcome::Unknown;
    }
}
