<?php

namespace App\Services\Assistant;

/**
 * Respuesta de una pregunta: `outcome` y `answer` del servidor, llamadas registradas y rondas con el proveedor.
 */
final readonly class AssistantAnswer
{
    /**
     * @param  list<ToolCallRecord>  $toolCalls
     */
    public function __construct(
        public Outcome $outcome,
        public string $answer,
        public array $toolCalls = [],
        public int $rounds = 0,
    ) {}
}
