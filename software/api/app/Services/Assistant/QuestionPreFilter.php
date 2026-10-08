<?php

namespace App\Services\Assistant;

/**
 * Filtro previo al proveedor (RN-10, design D7 paso 1): una pregunta que menciona pacientes, prescripciones,
 * recetas o fórmulas médicas, que pregunta qué se le dispensó a alguien, o que lleva 7 o más dígitos seguidos
 * (posible documento; puntos entre dígitos ignorados), nunca llega al modelo y responde `out_of_scope`.
 */
final class QuestionPreFilter
{
    /** Vocabulario clínico: el catálogo de herramientas no responde nada de esto. "formulario" no es una fórmula. */
    private const SENSITIVE_PATTERN = '/pacient|prescri|recet|\bformul(?!ario)/';

    /**
     * Dispensación dirigida a una persona: clítico de objeto indirecto ("le dispensaron", "les fue dispensado")
     * o destinatario tras el verbo ("dispensó a", "dispensado al", "dispensaron para"). "dispensar" sin
     * destinatario ("disponible para dispensar en", "se dispensaron de acetaminofén") sigue siendo inventario.
     */
    private const PERSON_DISPENSATION_PATTERN = '/\bles?\s+(?:\w+\s+)?dispens|dispens\w*\s+(?:a|al|para)\s/';

    private const DOCUMENT_PATTERN = '/\d{7,}/';

    public function blocks(string $question): bool
    {
        $text = Text::normalize($question);
        // 9.999.010.001 → 9999010001: los separadores de miles no esconden un documento.
        $digits = (string) preg_replace('/(?<=\d)\.(?=\d)/', '', $text);

        return preg_match(self::SENSITIVE_PATTERN, $text) === 1
            || preg_match(self::PERSON_DISPENSATION_PATTERN, $text) === 1
            || preg_match(self::DOCUMENT_PATTERN, $digits) === 1;
    }
}
