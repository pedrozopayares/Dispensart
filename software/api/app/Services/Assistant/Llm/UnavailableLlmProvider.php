<?php

namespace App\Services\Assistant\Llm;

use App\Exceptions\AssistantUnavailable;

/**
 * Enlace para un AI_PROVIDER desconocido (design D2): el arranque y las demás rutas siguen sanos; solo el
 * asistente responde 503. Nunca cae al `mock` en silencio.
 */
final class UnavailableLlmProvider implements LlmProvider
{
    public function name(): string
    {
        return 'unavailable';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        throw new AssistantUnavailable('Proveedor del asistente no configurado.');
    }
}
