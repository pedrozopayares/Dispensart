<?php

namespace App\Services\Assistant;

/**
 * Filtro previo al proveedor (RN-10, design D7 paso 1): una pregunta que menciona pacientes, prescripciones o
 * recetas, o que lleva 7 o más dígitos seguidos (posible documento; puntos entre dígitos ignorados), nunca llega
 * al modelo y responde `out_of_scope`.
 */
final class QuestionPreFilter
{
    private const SENSITIVE_PATTERN = '/pacient|prescrip|receta/';

    private const DOCUMENT_PATTERN = '/\d{7,}/';

    public function blocks(string $question): bool
    {
        $text = Text::normalize($question);
        // 9.999.010.001 → 9999010001: los separadores de miles no esconden un documento.
        $digits = (string) preg_replace('/(?<=\d)\.(?=\d)/', '', $text);

        return preg_match(self::SENSITIVE_PATTERN, $text) === 1
            || preg_match(self::DOCUMENT_PATTERN, $digits) === 1;
    }
}
