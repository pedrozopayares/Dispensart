<?php

namespace App\Services\Assistant\Llm;

/**
 * Respuesta de una ronda: llamadas a herramientas pedidas y, si no hay, texto final. El texto se descarta siempre
 * (design D8): solo señala que el modelo terminó.
 */
final readonly class ChatResponse
{
    /**
     * @param  list<ToolCall>  $toolCalls
     */
    public function __construct(
        public ?string $text,
        public array $toolCalls = [],
    ) {}

    public static function text(string $text): self
    {
        return new self($text);
    }

    /**
     * @param  list<ToolCall>  $toolCalls
     */
    public static function toolCalls(array $toolCalls): self
    {
        return new self(null, $toolCalls);
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
