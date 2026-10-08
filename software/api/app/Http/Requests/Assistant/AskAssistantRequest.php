<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pregunta al asistente (inventory-assistant «Pregunta en lenguaje natural»): texto de 3 a 500 caracteres. Toda
 * sesión puede preguntar (auth:sanctum en la ruta): sin authorize() propio, porque cada herramienta autoriza con la
 * Policy de su fuente y responde not_permitted, nunca 403 (proposal, supuesto 2).
 */
final class AskAssistantRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function question(): string
    {
        return $this->string('question')->toString();
    }
}
