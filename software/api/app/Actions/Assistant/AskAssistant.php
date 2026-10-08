<?php

namespace App\Actions\Assistant;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\AssistantOrchestrator;
use App\Services\Assistant\AssistantQueryLogger;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Outcome;
use App\Services\Assistant\QueryTrace;
use App\Services\Assistant\QuestionPreFilter;

/**
 * Caso de uso «preguntar al asistente» (inventory-assistant), compartido por la ruta y por `assistant:eval`.
 * Filtro previo de datos de pacientes antes del proveedor (RN-10), ciclo acotado y una línea de log sin
 * contenido en todo caso, también ante un 503. Nada se escribe en la base.
 */
final class AskAssistant
{
    public function __construct(
        private readonly QuestionPreFilter $preFilter,
        private readonly AssistantOrchestrator $orchestrator,
        private readonly AssistantQueryLogger $logger,
        private readonly LlmProvider $provider,
    ) {}

    /**
     * @throws AssistantUnavailable
     */
    public function handle(User $user, string $question): AssistantAnswer
    {
        $startedAt = hrtime(true);
        $trace = new QueryTrace;
        $outcome = AssistantQueryLogger::UNAVAILABLE;

        try {
            $answer = $this->preFilter->blocks($question)
                ? new AssistantAnswer(Outcome::OutOfScope, Outcome::OutOfScope->fixedMessage())
                : $this->orchestrator->answer($user, $question, $trace);
            $outcome = $answer->outcome->value;

            return $answer;
        } finally {
            $this->logger->log($outcome, $trace, $this->provider->name(), (hrtime(true) - $startedAt) / 1e6);
        }
    }
}
