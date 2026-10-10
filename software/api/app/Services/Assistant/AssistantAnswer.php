<?php

namespace App\Services\Assistant;

/**
 * Respuesta de una pregunta: `outcome` y `answer` del servidor, llamadas registradas, rondas con el proveedor y el
 * `id` del modelo que atendió (S15), sellado por el caso de uso también cuando responde el filtro previo.
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
        public ?string $model = null,
    ) {}

    public function withModel(string $model): self
    {
        return new self($this->outcome, $this->answer, $this->toolCalls, $this->rounds, $model);
    }
}
