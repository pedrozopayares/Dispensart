<?php

namespace App\Services\Assistant;

/**
 * Resultado de una pregunta, decidido por el servidor a partir de las llamadas, nunca por el texto del modelo.
 */
enum Outcome: string
{
    case Answered = 'answered';
    case NoResults = 'no_results';
    case OutOfScope = 'out_of_scope';
    case NotPermitted = 'not_permitted';
    case Unknown = 'unknown';

    /**
     * Mensaje fijo en español para todo resultado salvo `answered` (lang/es/assistant.php).
     */
    public function fixedMessage(): string
    {
        return (string) __('assistant.messages.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $outcome): string => $outcome->value, self::cases());
    }
}
