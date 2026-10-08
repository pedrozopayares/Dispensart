<?php

namespace Tests\Support;

use App\Services\Assistant\Llm\ChatRequest;
use App\Services\Assistant\Llm\ChatResponse;
use App\Services\Assistant\Llm\LlmProvider;
use Closure;
use RuntimeException;

/**
 * Doble del puerto que simula un modelo comprometido o defectuoso (design D15): responde por ronda lo guionado y
 * guarda cada ChatRequest recibido. Solo en pruebas del orquestador y de defensa; nunca sustituye al `mock` real
 * en las pruebas de la ruta o del comando.
 */
final class ScriptedLlmProvider implements LlmProvider
{
    /** @var list<ChatRequest> */
    public array $requests = [];

    /**
     * @param  list<ChatResponse|Closure(ChatRequest): ChatResponse>  $script  una entrada por ronda
     * @param  Closure(ChatRequest): ChatResponse|null  $always  respuesta de toda ronda fuera del guion
     * @param  int  $safetyCap  tope de la prueba: más rondas que esto es un bucle sin límite en el código
     */
    public function __construct(
        private readonly array $script = [],
        private readonly ?Closure $always = null,
        private readonly int $safetyCap = 20,
    ) {}

    public function name(): string
    {
        return 'scripted';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $this->requests[] = $request;
        $round = count($this->requests) - 1;
        if ($round >= $this->safetyCap) {
            throw new RuntimeException('Tope de seguridad de la prueba: el ciclo no se detuvo.');
        }

        $step = $this->script[$round] ?? $this->always ?? ChatResponse::text('listo');

        return $step instanceof Closure ? $step($request) : $step;
    }
}
