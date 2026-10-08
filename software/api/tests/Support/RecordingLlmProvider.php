<?php

namespace Tests\Support;

use App\Services\Assistant\Llm\ChatRequest;
use App\Services\Assistant\Llm\ChatResponse;
use App\Services\Assistant\Llm\LlmProvider;

/**
 * Decorador del proveedor real enlazado (el `mock` por defecto): guarda cada carga enviada y nunca cambia la
 * respuesta (design D15). Sirve para contar llamadas y barrer lo que recibió el proveedor.
 */
final class RecordingLlmProvider implements LlmProvider
{
    /** @var list<ChatRequest> */
    public array $requests = [];

    public function __construct(private readonly LlmProvider $inner) {}

    public function name(): string
    {
        return $this->inner->name();
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $this->requests[] = $request;

        return $this->inner->chat($request);
    }

    /**
     * Todo lo enviado al proveedor (instrucciones, mensajes y argumentos), serializado para barridos.
     */
    public function payload(): string
    {
        return (string) json_encode(array_map(fn (ChatRequest $request): array => [
            'system' => $request->system,
            'messages' => array_map(fn ($message): array => (array) $message, $request->messages),
        ], $this->requests), JSON_UNESCAPED_UNICODE);
    }
}
