<?php

namespace App\Services\Assistant\Evaluation;

use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\Text;
use App\Services\Assistant\ToolCallRecord;
use App\Services\Assistant\Tools\CatalogResolver;

/**
 * Compara una respuesta con lo esperado y devuelve la primera expectativa incumplida, o null si acierta
 * (design D14). Orden: outcome → secuencia exacta de herramienta + estado → argumentos esperados como
 * subconjunto → fragmentos que `answer` debe contener → fragmentos que no debe contener.
 * Argumentos: `warehouse` y `product` por la entidad del catálogo que resuelven, con el mismo CatalogResolver de
 * las herramientas (misma bodega o producto = cumple; obtenido ambiguo o sin resolver = no cumple); si el esperado
 * no resuelve, y en los demás argumentos de texto, sin tildes ni mayúsculas por contención; enteros iguales.
 */
final class EvaluationMatcher
{
    /** Argumentos que las herramientas resuelven contra el catálogo. */
    private const CATALOG_ARGUMENTS = ['warehouse', 'product'];

    public function __construct(private readonly CatalogResolver $catalog) {}

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
                if (! $this->argumentMatches((string) $name, $value, $arguments[$name] ?? null)) {
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

    private function argumentMatches(string $name, mixed $expected, mixed $actual): bool
    {
        if (is_string($expected) && in_array($name, self::CATALOG_ARGUMENTS, true)) {
            $expectedId = $this->resolve($name, $expected);
            if ($expectedId !== null) {
                return is_string($actual) && $this->resolve($name, $actual) === $expectedId;
            }
        }
        if (is_int($expected)) {
            return $actual === $expected;
        }
        if (is_string($expected)) {
            return is_string($actual) && str_contains(Text::normalize($actual), Text::normalize($expected));
        }

        return $actual === $expected;
    }

    /**
     * Id de la bodega o del producto que resuelve el texto; null si es ambiguo o no coincide.
     */
    private function resolve(string $name, string $text): ?int
    {
        return $name === 'warehouse' ? $this->catalog->warehouseId($text) : $this->catalog->productId($text);
    }
}
