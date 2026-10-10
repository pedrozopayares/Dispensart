<?php

namespace App\Rules;

use App\Services\Assistant\Llm\ModelCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `model` de la pregunta (S15, design D5): solo un `id` de la lista de modelos disponibles. `mock` y prefijos ajenos
 * se deciden sin red; ningún texto del cliente llega a Ollama salvo un nombre que Ollama mismo listó.
 */
final readonly class AvailableModel implements ValidationRule
{
    public function __construct(private ModelCatalog $catalog) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->catalog->contains($value)) {
            $fail('assistant.model_unavailable')->translate();
        }
    }
}
