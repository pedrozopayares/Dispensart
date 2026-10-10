<?php

namespace App\Http\Resources;

use App\Services\Assistant\Llm\ModelChoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un modelo elegible del asistente (S15, design D6): solo `id`, `provider` y `name`. Nunca la URL de Ollama,
 * tamaños, digests, fechas ni capacidades.
 *
 * @mixin ModelChoice
 */
final class AssistantModelResource extends JsonResource
{
    /**
     * @return array{id: string, provider: 'mock'|'ollama', name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            // `mock` u `ollama:<name>`: el valor que acepta `model` en POST /assistant/ask.
            'id' => $this->id(),
            /** @var 'mock'|'ollama' */
            'provider' => $this->provider,
            'name' => $this->name,
        ];
    }
}
