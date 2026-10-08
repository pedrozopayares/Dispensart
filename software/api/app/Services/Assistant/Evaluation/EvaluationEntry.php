<?php

namespace App\Services\Assistant\Evaluation;

use App\Enums\Role;

/**
 * Una pregunta del conjunto de evaluación con su respuesta esperada (design D14). Ya validada por EvaluationSet.
 */
final readonly class EvaluationEntry
{
    /**
     * @param  list<string>  $tags
     * @param  list<array{tool: string, status: string, arguments: array<string, mixed>}>  $tools
     * @param  list<string>  $answerContains
     * @param  list<string>  $answerExcludes
     */
    public function __construct(
        public string $id,
        public array $tags,
        public Role $role,
        public string $question,
        public string $outcome,
        public array $tools,
        public array $answerContains,
        public array $answerExcludes,
    ) {}

    /**
     * Copia con los marcadores `{transfer:<alias>}` reemplazados por los ids sembrados (en la pregunta y en los
     * argumentos esperados; un argumento que es solo el marcador pasa a entero).
     *
     * @param  array<string, int>  $transfers
     */
    public function resolve(array $transfers): self
    {
        $replace = fn (string $text): string => (string) preg_replace_callback(
            EvaluationSet::MARKER,
            fn (array $match): string => (string) $transfers[$match[1]],
            $text,
        );

        $tools = [];
        foreach ($this->tools as $tool) {
            foreach ($tool['arguments'] as $name => $value) {
                if (is_string($value) && preg_match('/^'.trim(EvaluationSet::MARKER, '/').'$/', $value, $match) === 1) {
                    $tool['arguments'][$name] = $transfers[$match[1]];
                }
            }
            $tools[] = $tool;
        }

        return new self($this->id, $this->tags, $this->role, $replace($this->question), $this->outcome, $tools, $this->answerContains, $this->answerExcludes);
    }
}
