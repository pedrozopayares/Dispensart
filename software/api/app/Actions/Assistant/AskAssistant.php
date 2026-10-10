<?php

namespace App\Actions\Assistant;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\AssistantOrchestrator;
use App\Services\Assistant\AssistantQueryLogger;
use App\Services\Assistant\Llm\LlmProviderResolver;
use App\Services\Assistant\Llm\ModelCatalog;
use App\Services\Assistant\Llm\ModelChoice;
use App\Services\Assistant\Outcome;
use App\Services\Assistant\QueryTrace;
use App\Services\Assistant\QuestionPreFilter;

/**
 * Caso de uso «preguntar al asistente» (inventory-assistant), compartido por la ruta y por `assistant:eval`.
 * Filtro previo de datos de pacientes antes del proveedor (RN-10), ciclo acotado y una línea de log sin
 * contenido en todo caso, también ante un 503. Nada se escribe en la base.
 * Proveedor por pregunta (S15, design D4): el de la elección validada o, sin ella, el de AI_PROVIDER; el `id` del
 * modelo queda sellado en la respuesta.
 */
final class AskAssistant
{
    public function __construct(
        private readonly QuestionPreFilter $preFilter,
        private readonly AssistantOrchestrator $orchestrator,
        private readonly AssistantQueryLogger $logger,
        private readonly LlmProviderResolver $providers,
        private readonly ModelCatalog $catalog,
    ) {}

    /**
     * @throws AssistantUnavailable
     */
    public function handle(User $user, string $question, ?ModelChoice $choice = null): AssistantAnswer
    {
        $startedAt = hrtime(true);
        $provider = $this->providers->for($choice);
        $model = ($choice ?? $this->catalog->defaultChoice())->id();
        $trace = new QueryTrace;
        $outcome = AssistantQueryLogger::UNAVAILABLE;

        try {
            $answer = $this->preFilter->blocks($question)
                ? new AssistantAnswer(Outcome::OutOfScope, Outcome::OutOfScope->fixedMessage())
                : $this->orchestrator->answer($user, $question, $provider, $trace);
            $outcome = $answer->outcome->value;

            return $answer->withModel($model);
        } finally {
            $this->logger->log($outcome, $trace, $provider->name(), $model, (hrtime(true) - $startedAt) / 1e6);
        }
    }
}
