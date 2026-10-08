<?php

namespace App\Services\Assistant\Llm;

/**
 * Mensaje de la conversación con el proveedor. La pregunta viaja solo como `user`; los resultados de herramientas
 * solo como `tool`, dentro del sobre de texto no confiable (design D9). Las instrucciones no son un mensaje: van
 * aparte en ChatRequest::$system.
 */
final readonly class ChatMessage
{
    public const USER = 'user';

    public const ASSISTANT = 'assistant';

    public const TOOL = 'tool';

    /**
     * @param  list<ToolCall>  $toolCalls
     */
    public function __construct(
        public string $role,
        public string $content,
        public array $toolCalls = [],
        public ?string $toolName = null,
        public ?string $toolCallId = null,
    ) {}

    public static function user(string $question): self
    {
        return new self(self::USER, $question);
    }

    /**
     * @param  list<ToolCall>  $toolCalls
     */
    public static function assistant(array $toolCalls): self
    {
        return new self(self::ASSISTANT, '', $toolCalls);
    }

    public static function tool(string $toolName, string $callId, string $envelope): self
    {
        return new self(self::TOOL, $envelope, [], $toolName, $callId);
    }
}
