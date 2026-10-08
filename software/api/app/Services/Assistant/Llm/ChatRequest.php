<?php

namespace App\Services\Assistant\Llm;

/**
 * Una ronda con el proveedor: instrucciones fijas del servidor, conversación y catálogo cerrado de herramientas.
 * `timeoutSeconds` es el tiempo que queda del plazo total de la pregunta (design D7).
 */
final readonly class ChatRequest
{
    /**
     * @param  list<ChatMessage>  $messages
     * @param  list<ToolDefinition>  $tools
     */
    public function __construct(
        public string $system,
        public array $messages,
        public array $tools,
        public float $timeoutSeconds,
    ) {}

    /**
     * Llamadas a herramientas ya hechas en la conversación: base para numerar las nuevas sin repetir id.
     */
    public function toolCallsSoFar(): int
    {
        $count = 0;
        foreach ($this->messages as $message) {
            $count += count($message->toolCalls);
        }

        return $count;
    }

    public function hasToolResults(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->role === ChatMessage::TOOL) {
                return true;
            }
        }

        return false;
    }

    public function firstUserMessage(): string
    {
        foreach ($this->messages as $message) {
            if ($message->role === ChatMessage::USER) {
                return $message->content;
            }
        }

        return '';
    }
}
