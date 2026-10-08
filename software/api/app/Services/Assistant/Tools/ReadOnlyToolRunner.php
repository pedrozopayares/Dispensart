<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\ToolCallRecord;
use App\Services\Assistant\ToolCallStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Ejecuta una herramienta ya validada (design D4, D5):
 * 1. autoriza con la Policy de su fuente sobre el usuario de la petición; negada → `denied`, sin abrir
 *    transacción ni consultar;
 * 2. abre una transacción (o un punto de guardado si ya hay una), la pasa a solo lectura con `SET LOCAL`, ejecuta,
 *    materializa el resultado y revierte siempre. Una escritura la rechaza PostgreSQL (SQLSTATE 25006) → `failed`.
 * Cualquier otro error de base se propaga: un fallo de infraestructura no se disfraza de respuesta.
 */
final class ReadOnlyToolRunner
{
    private const READ_ONLY_VIOLATION = '25006';

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function run(User $user, AssistantTool $tool, array $arguments): ToolCallRecord
    {
        if (! Gate::forUser($user)->allows('viewAny', $tool->policySubject())) {
            return new ToolCallRecord($tool->name(), ToolCallStatus::Denied, $arguments);
        }

        DB::beginTransaction();
        try {
            DB::statement('SET LOCAL transaction_read_only = on');
            $data = $tool->run($arguments);
        } catch (QueryException $e) {
            if ($e->getCode() !== self::READ_ONLY_VIOLATION) {
                throw $e;
            }

            return new ToolCallRecord($tool->name(), ToolCallStatus::Failed, $arguments);
        } finally {
            DB::rollBack();
        }

        return new ToolCallRecord($tool->name(), ToolCallStatus::Ok, $arguments, $data);
    }
}
