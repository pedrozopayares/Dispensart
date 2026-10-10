<?php

namespace App\Services\Assistant\Llm;

/**
 * Modelo que atiende una pregunta (S15, design D1): `mock` u `ollama:<name>`, partido en el primer `:` porque los
 * nombres de Ollama llevan etiqueta (`gemma4:e2b-mlx`). El nombre se limita a un alfabeto seguro: ningún `id`
 * ofrecido o aceptado lleva marcado, espacios ni caracteres de control. `unavailable` solo representa un
 * AI_PROVIDER desconocido; `parse()` nunca lo produce.
 */
final readonly class ModelChoice
{
    public const MOCK = 'mock';

    public const OLLAMA = 'ollama';

    public const UNAVAILABLE = 'unavailable';

    private const NAME_PATTERN = '~^[A-Za-z0-9_][A-Za-z0-9_.:/-]{0,199}$~D';

    private function __construct(
        public string $provider,
        public string $name,
    ) {}

    public static function mock(): self
    {
        return new self(self::MOCK, self::MOCK);
    }

    public static function unavailable(): self
    {
        return new self(self::UNAVAILABLE, self::UNAVAILABLE);
    }

    /**
     * Modelo de Ollama por su nombre; `null` si el nombre sale del alfabeto seguro.
     */
    public static function ollama(string $name): ?self
    {
        return preg_match(self::NAME_PATTERN, $name) === 1 ? new self(self::OLLAMA, $name) : null;
    }

    /**
     * `mock` u `ollama:<name>` bien formado; cualquier otra forma → `null`.
     */
    public static function parse(string $id): ?self
    {
        if ($id === self::MOCK) {
            return self::mock();
        }

        $parts = explode(':', $id, 2);

        return count($parts) === 2 && $parts[0] === self::OLLAMA ? self::ollama($parts[1]) : null;
    }

    public function id(): string
    {
        return $this->provider === self::OLLAMA ? self::OLLAMA.':'.$this->name : $this->provider;
    }
}
