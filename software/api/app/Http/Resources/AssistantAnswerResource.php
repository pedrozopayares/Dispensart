<?php

namespace App\Http\Resources;

use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\Outcome;
use App\Services\Assistant\ToolCallRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Respuesta del asistente bajo `data` (design § API contract): `outcome` y `answer` del servidor y las llamadas
 * hechas. Nunca resultados de herramientas ni texto del modelo.
 *
 * @mixin AssistantAnswer
 */
final class AssistantAnswerResource extends JsonResource
{
    /**
     * @return array{outcome: Outcome, answer: string, tool_calls: list<AssistantToolCallResource>, model: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'outcome' => $this->outcome,
            'answer' => $this->answer,
            'tool_calls' => collect($this->toolCalls)
                ->map(fn (ToolCallRecord $call): AssistantToolCallResource => new AssistantToolCallResource($call))
                ->values()->all(),
            // `id` del modelo que atendió (`mock` u `ollama:<name>`; S15).
            'model' => (string) $this->model,
        ];
    }
}
