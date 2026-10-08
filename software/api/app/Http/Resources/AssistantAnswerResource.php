<?php

namespace App\Http\Resources;

use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\ToolCallRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

/**
 * Respuesta del asistente bajo `data` (design § API contract). `arguments` siempre objeto JSON: los validados, o
 * `{}` si la llamada fue rechazada o tuvo argumentos fuera de esquema. Nunca resultados de herramientas ni texto
 * del modelo.
 *
 * @mixin AssistantAnswer
 */
final class AssistantAnswerResource extends JsonResource
{
    /**
     * @return array{outcome: 'answered'|'no_results'|'out_of_scope'|'not_permitted'|'unknown', answer: string, tool_calls: list<array{tool: string, arguments: stdClass, status: 'ok'|'denied'|'rejected'|'invalid_arguments'|'failed'}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'outcome' => $this->outcome->value,
            'answer' => $this->answer,
            'tool_calls' => array_map(fn (ToolCallRecord $call): array => [
                'tool' => $call->tool,
                'arguments' => (object) $call->arguments,
                'status' => $call->status->value,
            ], $this->toolCalls),
        ];
    }
}
