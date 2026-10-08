<?php

namespace App\Services\Assistant;

/**
 * Llamada registrada de una pregunta. `arguments` son los ya validados (ok, denied, failed) o `[]` (rejected,
 * invalid_arguments): nunca se devuelve salida del modelo sin validar (design D9). `data` solo en `ok`.
 */
final readonly class ToolCallRecord
{
    /** Largo máximo del nombre de herramienta devuelto y registrado. */
    public const MAX_TOOL_NAME = 64;

    public string $tool;

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array{items: list<array<string, mixed>>, meta: array<string, mixed>}|null  $data
     */
    public function __construct(
        string $tool,
        public ToolCallStatus $status,
        public array $arguments = [],
        public ?array $data = null,
    ) {
        $this->tool = self::safeName($tool);
    }

    /**
     * Nombre apto para respuesta, log y cabecera del sobre: solo letras, dígitos, guion y guion bajo, a lo sumo
     * 64 caracteres. El nombre de una llamada rechazada lo inventó el modelo: nunca viaja tal cual (RN-10).
     */
    public static function safeName(string $value): string
    {
        return mb_substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $value), 0, self::MAX_TOOL_NAME);
    }

    public function hasData(): bool
    {
        return $this->status === ToolCallStatus::Ok && $this->data !== null && $this->data['items'] !== [];
    }
}
