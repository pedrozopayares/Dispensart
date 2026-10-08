<?php

namespace App\Services\Assistant\Llm;

/**
 * Herramienta ofrecida al modelo: nombre, descripción y el mismo esquema que usa el validador (design D3).
 */
final readonly class ToolDefinition
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
    ) {}
}
