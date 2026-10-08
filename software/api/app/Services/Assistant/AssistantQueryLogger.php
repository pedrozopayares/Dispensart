<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Log;

/**
 * Una línea `assistant.query` por pregunta (design D13; RN-10): outcome (o `assistant_unavailable`), nombre y
 * estado de cada llamada, rondas, proveedor y duración. `correlation_id` lo agrega el formateador desde Context.
 * Nunca la pregunta, la respuesta, los argumentos ni los resultados: la regla no tiene excepciones.
 */
final class AssistantQueryLogger
{
    public const UNAVAILABLE = 'assistant_unavailable';

    public function log(string $outcome, QueryTrace $trace, string $provider, float $durationMs): void
    {
        Log::info('assistant.query', [
            'outcome' => $outcome,
            'tool_calls' => array_map(
                fn (ToolCallRecord $call): array => ['tool' => $call->tool, 'status' => $call->status->value],
                $trace->calls,
            ),
            'rounds' => $trace->rounds,
            'provider' => $provider,
            'duration_ms' => round($durationMs, 2),
        ]);
    }
}
