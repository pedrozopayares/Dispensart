<?php

namespace App\Http\Resources;

use App\Services\Assistant\ToolCallRecord;
use App\Services\Assistant\ToolCallStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una llamada a herramienta de la respuesta del asistente: nombre (recortado a 64), argumentos validados o `{}` si
 * la llamada fue rechazada o tuvo argumentos fuera de esquema (design D9), y estado. Nunca el resultado.
 *
 * @mixin ToolCallRecord
 */
final class AssistantToolCallResource extends JsonResource
{
    /**
     * @return array{tool: string, arguments: object, status: ToolCallStatus}
     */
    public function toArray(Request $request): array
    {
        return [
            'tool' => $this->tool,
            /**
             * Argumentos validados contra el esquema de la herramienta. Siempre objeto JSON, también vacío: `{}`.
             *
             * @var array<string, string|int>
             */
            'arguments' => (object) $this->arguments,
            'status' => $this->status,
        ];
    }
}
