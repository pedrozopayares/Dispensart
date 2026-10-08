<?php

namespace App\Services\Assistant;

/**
 * Sobre del resultado de una herramienta para el modelo (design D9): delimitadores fijos marcados como texto no
 * confiable y JSON con `<`, `>` y `&` escapados (`JSON_HEX_TAG | JSON_HEX_AMP`), de modo que ningún contenido,
 * incluida una observación de traslado, puede falsificar el cierre ni salir del sobre. Un error viaja como
 * {"error": "<status>"} sin datos.
 */
final class ToolResultEnvelope
{
    private const FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public function wrap(ToolCallRecord $call, string $callId): string
    {
        $payload = $call->status === ToolCallStatus::Ok ? ($call->data ?? []) : ['error' => $call->status->value];
        $id = self::attribute($callId);
        $tool = self::attribute($call->tool);

        return "<<<TOOL_RESULT tool=\"{$tool}\" call=\"{$id}\" trust=\"untrusted\">>>\n"
            .json_encode($payload, self::FLAGS)
            ."\n<<<END_TOOL_RESULT call=\"{$id}\">>>";
    }

    /**
     * Atributo de cabecera: solo letras, dígitos, guion y guion bajo (el id lo eligió el proveedor).
     */
    private static function attribute(string $value): string
    {
        return mb_substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $value), 0, ToolCallRecord::MAX_TOOL_NAME);
    }
}
