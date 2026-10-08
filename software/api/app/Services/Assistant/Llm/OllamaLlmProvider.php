<?php

namespace App\Services\Assistant\Llm;

use App\Exceptions\AssistantUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Proveedor Ollama local (AI_PROVIDER=ollama; design D11): `POST {base_url}/api/chat` nativo, sin streaming y con
 * temperatura 0. Sin llave ni servicio de pago. Conexión 2 s; lectura = min(OLLAMA_TIMEOUT, tiempo restante del
 * plazo). Conexión fallida, HTTP 4xx/5xx, tiempo agotado o JSON sin `message` → AssistantUnavailable, sin URL ni
 * texto del proveedor en la excepción. No registra cuerpos.
 */
final class OllamaLlmProvider implements LlmProvider
{
    private const CONNECT_TIMEOUT = 2;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly float $timeout,
    ) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(max(1, (int) ceil(min($this->timeout, $request->timeoutSeconds))))
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/api/chat', $this->payload($request))
                ->throw();
        } catch (ConnectionException|RequestException) {
            throw new AssistantUnavailable('El proveedor Ollama no respondió correctamente.');
        }

        $message = $response->json('message');
        if (! is_array($message)) {
            throw new AssistantUnavailable('Respuesta de Ollama sin mensaje.');
        }

        return $this->toResponse($message, $request->toolCallsSoFar());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ChatRequest $request): array
    {
        $messages = [['role' => 'system', 'content' => $request->system]];
        foreach ($request->messages as $message) {
            $messages[] = match ($message->role) {
                ChatMessage::ASSISTANT => [
                    'role' => 'assistant',
                    'content' => $message->content,
                    'tool_calls' => array_map(fn (ToolCall $call): array => [
                        'function' => ['name' => $call->name, 'arguments' => is_array($call->arguments) ? (object) $call->arguments : $call->arguments],
                    ], $message->toolCalls),
                ],
                ChatMessage::TOOL => ['role' => 'tool', 'content' => $message->content, 'tool_name' => $message->toolName],
                default => ['role' => 'user', 'content' => $message->content],
            };
        }

        return [
            'model' => $this->model,
            'messages' => $messages,
            'tools' => array_map(fn (ToolDefinition $tool): array => [
                'type' => 'function',
                'function' => ['name' => $tool->name, 'description' => $tool->description, 'parameters' => $tool->parameters],
            ], $request->tools),
            'stream' => false,
            'options' => ['temperature' => 0],
        ];
    }

    /**
     * Ids `c<n>` generados por el servidor, continuando la numeración de la conversación.
     *
     * @param  array<mixed>  $message
     */
    private function toResponse(array $message, int $offset): ChatResponse
    {
        $calls = [];
        foreach (is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [] as $raw) {
            $function = is_array($raw) && is_array($raw['function'] ?? null) ? $raw['function'] : [];
            $arguments = $function['arguments'] ?? [];
            $calls[] = new ToolCall(
                'c'.($offset + count($calls) + 1),
                is_string($function['name'] ?? null) ? $function['name'] : '',
                is_array($arguments) || is_string($arguments) ? $arguments : '',
            );
        }

        if ($calls !== []) {
            return ChatResponse::toolCalls($calls);
        }

        return ChatResponse::text(is_string($message['content'] ?? null) ? $message['content'] : '');
    }
}
