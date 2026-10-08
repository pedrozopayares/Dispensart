<?php

namespace App\Services\Assistant\Llm;

use App\Exceptions\AssistantUnavailable;

/**
 * Puerto del modelo de lenguaje (design D2). El resto del asistente depende solo de esta interfaz; el proveedor
 * concreto lo elige AssistantServiceProvider por AI_PROVIDER. El modelo solo elige herramientas y argumentos:
 * su texto nunca llega al usuario (design D8).
 */
interface LlmProvider
{
    /**
     * Nombre corto para el log: 'mock', 'ollama' o 'unavailable'.
     */
    public function name(): string;

    /**
     * @throws AssistantUnavailable si el proveedor no responde, responde con error o no es válido
     */
    public function chat(ChatRequest $request): ChatResponse;
}
