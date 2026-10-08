<?php

namespace App\Services\Assistant\Tools;

/**
 * Subconjunto de JSON Schema usado por las 4 herramientas (design D3): objeto con `additionalProperties: false`,
 * `required`, y por propiedad `type` (`string`, `integer` estricto), `minimum`, `maximum`, `maxLength` y `enum`.
 * Los argumentos en texto JSON se decodifican; lo que no es objeto es inválido. Un argumento fuera del esquema
 * (`sql`, `role`, `user_id`) invalida la llamada completa: nunca se descarta en silencio.
 */
final class ToolArgumentValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<mixed>|string  $arguments
     * @return array<string, mixed>|null argumentos válidos, o null si no cumplen el esquema
     */
    public function validate(array $schema, array|string $arguments): ?array
    {
        if (is_string($arguments)) {
            $decoded = json_decode($arguments === '' ? '{}' : $arguments, true);
            if (! is_array($decoded)) {
                return null;
            }
            $arguments = $decoded;
        }
        if ($arguments !== [] && array_is_list($arguments)) {
            return null;
        }

        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'] ?? [];
        /** @var list<string> $required */
        $required = $schema['required'] ?? [];

        foreach ($required as $name) {
            if (! array_key_exists($name, $arguments)) {
                return null;
            }
        }

        $valid = [];
        foreach ($arguments as $name => $value) {
            if (! is_string($name) || ! isset($properties[$name]) || ! $this->matches($properties[$name], $value)) {
                return null;
            }
            $valid[$name] = $value;
        }

        return $valid;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function matches(array $rule, mixed $value): bool
    {
        $type = $rule['type'] ?? null;

        if ($type === 'integer') {
            return is_int($value)
                && (! isset($rule['minimum']) || $value >= $rule['minimum'])
                && (! isset($rule['maximum']) || $value <= $rule['maximum']);
        }

        if ($type === 'string') {
            return is_string($value)
                && (! isset($rule['maxLength']) || mb_strlen($value) <= $rule['maxLength'])
                && (! isset($rule['enum']) || in_array($value, (array) $rule['enum'], true));
        }

        return false;
    }
}
