<?php

namespace App\Services\Assistant\Llm;

/**
 * Llamada a herramienta pedida por el modelo. Nombre y argumentos son salida del modelo: no confiables hasta que
 * el catálogo y el validador los aceptan (design D3).
 */
final readonly class ToolCall
{
    /**
     * @param  array<mixed>|string  $arguments  objeto ya decodificado o texto JSON
     */
    public function __construct(
        public string $id,
        public string $name,
        public array|string $arguments = [],
    ) {}
}
