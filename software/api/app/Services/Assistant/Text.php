<?php

namespace App\Services\Assistant;

use Illuminate\Support\Str;

/**
 * Normalización única del asistente: sin tildes, minúsculas y espacios simples. La usan el filtro previo, el
 * resolutor de catálogo, el modo simulado y el comparador de la evaluación.
 */
final class Text
{
    private const STOPWORDS = ['de', 'del', 'la', 'las', 'el', 'los', 'en', 'y', 'a', 'al', 'por', 'para', 'con'];

    public static function normalize(string $text): string
    {
        $ascii = Str::lower(Str::ascii($text));

        return trim((string) preg_replace('/\s+/', ' ', $ascii));
    }

    /**
     * Palabras significativas (letras y dígitos, sin palabras vacías) del texto normalizado.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $parts = preg_split('/[^a-z0-9]+/', self::normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($parts, fn (string $word): bool => ! in_array($word, self::STOPWORDS, true)));
    }
}
