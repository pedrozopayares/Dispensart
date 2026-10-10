<?php

namespace App\Http\Requests\Assistant;

use App\Rules\AvailableModel;
use App\Services\Assistant\Llm\ModelCatalog;
use App\Services\Assistant\Llm\ModelChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pregunta al asistente (inventory-assistant «Pregunta en lenguaje natural»): texto de 3 a 500 caracteres. Toda
 * sesión puede preguntar (auth:sanctum en la ruta): sin authorize() propio, porque cada herramienta autoriza con la
 * Policy de su fuente y responde not_permitted, nunca 403 (proposal, supuesto 2).
 * `model` opcional (S15, design D5): texto y, solo entonces, un `id` de la lista de modelos disponibles.
 */
final class AskAssistantRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(ModelCatalog $catalog): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:500'],
            /**
             * Modelo que atiende la pregunta: `mock` u `ollama:<name>` de GET /assistant/models. Sin él decide
             * AI_PROVIDER. Fuera de la lista: 422 con `errors.model`.
             *
             * @example ollama:gemma4:e2b-mlx
             */
            'model' => ['sometimes', 'bail', 'string', 'max:220', new AvailableModel($catalog)],
        ];
    }

    public function question(): string
    {
        return $this->string('question')->toString();
    }

    /**
     * Modelo elegido, ya validado; `null` si la pregunta no trae `model`.
     */
    public function model(): ?ModelChoice
    {
        $model = $this->validated('model');

        return is_string($model) ? ModelChoice::parse($model) : null;
    }
}
