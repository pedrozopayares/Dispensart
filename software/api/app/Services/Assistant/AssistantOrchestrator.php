<?php

namespace App\Services\Assistant;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use App\Services\Assistant\Llm\ChatMessage;
use App\Services\Assistant\Llm\ChatRequest;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\ToolCall;
use App\Services\Assistant\Tools\ReadOnlyToolRunner;
use App\Services\Assistant\Tools\ToolArgumentValidator;
use App\Services\Assistant\Tools\ToolRegistry;

/**
 * Ciclo proveedor ↔ herramientas acotado (design D7, D9):
 * - instrucciones fijas (SystemPrompt) y la pregunta solo como mensaje `user`;
 * - por ronda: si alguna llamada está fuera del catálogo → `rejected`, ninguna de la ronda se ejecuta y fin;
 *   si no, cada llamada se valida, se autoriza y se ejecuta en solo lectura, y su resultado vuelve como `tool`
 *   dentro del sobre no confiable;
 * - como máximo `max_tool_calls` llamadas y `max_rounds` rondas; al límite → `unknown` sin más rondas;
 * - plazo total `deadline_seconds`: cada ronda recibe el tiempo restante; agotado → 503;
 * - `outcome` y `answer` los decide el servidor; el texto del modelo se descarta siempre.
 */
final class AssistantOrchestrator
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolArgumentValidator $validator,
        private readonly ReadOnlyToolRunner $runner,
        private readonly ToolResultEnvelope $envelope,
        private readonly OutcomeResolver $outcomes,
        private readonly AnswerComposer $composer,
    ) {}

    /**
     * @param  LlmProvider  $provider  el de la elección de la pregunta o el de AI_PROVIDER (S15, design D4)
     * @param  QueryTrace|null  $trace  se actualiza en cada ronda y llamada (lo lee el log aun ante un 503)
     *
     * @throws AssistantUnavailable
     */
    public function answer(User $user, string $question, LlmProvider $provider, ?QueryTrace $trace = null): AssistantAnswer
    {
        $trace ??= new QueryTrace;
        $maxCalls = (int) config('assistant.max_tool_calls');
        $maxRounds = (int) config('assistant.max_rounds');
        $deadline = hrtime(true) + (int) ((float) config('assistant.deadline_seconds') * 1e9);

        $messages = [ChatMessage::user($question)];
        $limitHit = false;

        while (true) {
            if ($trace->rounds === $maxRounds) {
                // La última ronda permitida pidió herramientas: no hay otra para cerrar.
                $limitHit = true;
                break;
            }
            $remaining = ($deadline - hrtime(true)) / 1e9;
            if ($remaining <= 0) {
                throw new AssistantUnavailable('Plazo del asistente agotado.');
            }

            $trace->rounds++;
            $response = $provider->chat(new ChatRequest(SystemPrompt::TEXT, $messages, $this->registry->definitions(), $remaining));
            if (! $response->hasToolCalls()) {
                break;
            }

            $unknown = array_filter($response->toolCalls, fn (ToolCall $call): bool => $this->registry->find($call->name) === null);
            if ($unknown !== []) {
                foreach ($unknown as $call) {
                    $trace->calls[] = new ToolCallRecord($call->name, ToolCallStatus::Rejected);
                }
                break;
            }

            $messages[] = ChatMessage::assistant($response->toolCalls);
            foreach ($response->toolCalls as $call) {
                if (count($trace->calls) >= $maxCalls) {
                    $limitHit = true;
                    break 2;
                }
                $record = $this->execute($user, $call);
                $trace->calls[] = $record;
                $messages[] = ChatMessage::tool($record->tool, $call->id, $this->envelope->wrap($record, $call->id));
            }
        }

        $outcome = $this->outcomes->resolve($trace->calls, $limitHit);
        $answer = $outcome === Outcome::Answered ? $this->composer->compose($trace->calls) : $outcome->fixedMessage();

        return new AssistantAnswer($outcome, $answer, $trace->calls, $trace->rounds);
    }

    private function execute(User $user, ToolCall $call): ToolCallRecord
    {
        $tool = $this->registry->find($call->name);
        if ($tool === null) {
            return new ToolCallRecord($call->name, ToolCallStatus::Rejected);
        }

        $arguments = $this->validator->validate($tool->parameters(), $call->arguments);
        if ($arguments === null) {
            return new ToolCallRecord($tool->name(), ToolCallStatus::InvalidArguments);
        }

        return $this->runner->run($user, $tool, $arguments);
    }
}
