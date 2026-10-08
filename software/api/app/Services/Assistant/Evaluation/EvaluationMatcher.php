<?php

namespace App\Services\Assistant\Evaluation;

use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\Text;
use App\Services\Assistant\ToolCallRecord;

/**
 * Compara una respuesta con lo esperado y devuelve la primera expectativa incumplida, o null si acierta
 * (design D14). Orden: outcome → secuencia exacta de herramienta + estado → argumentos esperados como
 * subconjunto (texto sin tildes ni mayúsculas por contención; enteros iguales) → fragmentos que `answer` debe
 * contener → fragmentos que no debe contener.
 */
final class EvaluationMatcher
{
    public function firstFailure(EvaluationEntry $entry, AssistantAnswer $answer): ?string
    {
        if ($answer->outcome->value !== $entry->outcome) {
            return "outcome: esperado {$entry->outcome}, obtenido {$answer->outcome->value}";
        }

        $expected = array_map(fn (array $tool): string => $tool['tool'].':'.$tool['status'], $entry->tools);
        $actual = array_map(fn (ToolCallRecord $call): string => $call->tool.':'.$call->status->value, $answer->toolCalls);
        if ($expected !== $actual) {
            return 'herramientas: esperado ['.implode(', ', $expected).'], obtenido ['.implode(', ', $actual).']';
        }

        foreach ($entry->tools as $index => $tool) {
            $arguments = $answer->toolCalls[$index]->arguments;
            foreach ($tool['arguments'] as $name => $value) {
                if (! $this->argumentMatches($value, $arguments[$name] ?? null)) {
                    $got = array_key_exists($name, $arguments) ? json_encode($arguments[$name], JSON_UNESCAPED_UNICODE) : 'ausente';

                    return "argumento {$name} de {$tool['tool']}: esperado ".json_encode($value, JSON_UNESCAPED_UNICODE).", obtenido {$got}";
                }
            }
        }

        $text = Text::normalize($answer->answer);
        foreach ($entry->answerContains as $fragment) {
            if (! str_contains($text, Text::normalize($fragment))) {
                return "answer no contiene «{$fragment}»";
            }
        }
        foreach ($entry->answerExcludes as $fragment) {
            if (str_contains($text, Text::normalize($fragment))) {
                return "answer contiene «{$fragment}»";
            }
        }

        return null;
    }

    private function argumentMatches(mixed $expected, mixed $actual): bool
    {
        if (is_int($expected)) {
            return $actual === $expected;
        }
        if (is_string($expected)) {
            return is_string($actual) && str_contains(Text::normalize($actual), Text::normalize($expected));
        }

        return $actual === $expected;
    }
}
