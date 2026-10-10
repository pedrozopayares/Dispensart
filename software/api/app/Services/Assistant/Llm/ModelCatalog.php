<?php

namespace App\Services\Assistant\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Único punto que decide qué modelos son elegibles (S15, design D2 y D3): `mock` siempre y primero; un modelo de
 * Ollama solo si `/api/tags` lo lista y su ficha de `/api/show` declara `tools`, todo dentro de un plazo total
 * corto. Ollama caído, lento, con error o mal formado deja solo `mock`: ninguna excepción sale de aquí.
 * La lista de nombres se guarda poco tiempo en un almacén que no es la base (también vacía). No registra nada:
 * ni cuerpos ni la URL, que sale solo del entorno.
 */
final class ModelCatalog
{
    private const CACHE_PREFIX = 'assistant.models:';

    /**
     * @return list<ModelChoice>
     */
    public function available(): array
    {
        $ollama = array_map(fn (string $name): ?ModelChoice => ModelChoice::ollama($name), $this->ollamaNames());

        return [ModelChoice::mock(), ...array_values(array_filter($ollama))];
    }

    /**
     * `mock` y prefijos ajenos se deciden sin red; solo un `ollama:<name>` bien formado consulta el catálogo.
     */
    public function contains(string $id): bool
    {
        if ($id === ModelChoice::MOCK) {
            return true;
        }

        $choice = ModelChoice::parse($id);
        if ($choice === null || $choice->provider !== ModelChoice::OLLAMA) {
            return false;
        }

        return in_array($choice->id(), array_map(fn (ModelChoice $model): string => $model->id(), $this->available()), true);
    }

    /**
     * Modelo de las preguntas sin `model` y de `assistant:eval`, desde AI_PROVIDER. Sin red.
     */
    public function defaultChoice(): ModelChoice
    {
        $provider = config('assistant.provider');

        return match (is_string($provider) && $provider !== '' ? $provider : ModelChoice::MOCK) {
            ModelChoice::MOCK => ModelChoice::mock(),
            ModelChoice::OLLAMA => ModelChoice::ollama((string) config('assistant.ollama.model')) ?? ModelChoice::unavailable(),
            default => ModelChoice::unavailable(),
        };
    }

    /**
     * Nombres de Ollama elegibles, de la caché o de un descubrimiento nuevo. Solo cadenas: la caché de Laravel no
     * deserializa clases.
     *
     * @return list<string>
     */
    private function ollamaNames(): array
    {
        $baseUrl = rtrim((string) config('assistant.ollama.base_url'), '/');

        // El almacén es externo al proceso: lo leído se trata como dato no confiable.
        /** @var mixed $names */
        $names = Cache::store((string) config('assistant.models.cache_store'))->remember(
            self::CACHE_PREFIX.hash('sha256', $baseUrl),
            (int) config('assistant.models.cache_ttl_seconds'),
            fn (): array => $this->discover($baseUrl),
        );

        return is_array($names) ? array_values(array_filter($names, 'is_string')) : [];
    }

    /**
     * @return list<string> nombres con `tools`, ordenados
     */
    private function discover(string $baseUrl): array
    {
        $deadline = hrtime(true) + (int) ((float) config('assistant.models.budget_seconds') * 1e9);

        // Un plazo de cero en Guzzle significa «sin límite»: sin presupuesto no se consulta.
        $remaining = $this->remaining($deadline);
        if ($remaining <= 0) {
            return [];
        }

        try {
            $tags = Http::connectTimeout(min(1, $remaining))
                ->timeout($remaining)
                ->acceptJson()
                ->get($baseUrl.'/api/tags')
                ->throw()
                ->json('models');
        } catch (ConnectionException|RequestException) {
            return [];
        }

        $names = $this->validNames($tags);
        if ($names === []) {
            return [];
        }

        // Plazo agotado tras /api/tags: no se espera ninguna ficha.
        $remaining = $this->remaining($deadline);
        if ($remaining <= 0) {
            return [];
        }

        // Todas las fichas en una sola ronda; cada elemento que falla se omite sin afectar a los demás.
        $responses = Http::pool(function (Pool $pool) use ($names, $baseUrl, $remaining): void {
            foreach ($names as $index => $name) {
                $pool->as('m'.$index)
                    ->connectTimeout(min(1, $remaining))
                    ->timeout($remaining)
                    ->acceptJson()
                    ->post($baseUrl.'/api/show', ['model' => $name]);
            }
        });

        $kept = [];
        foreach ($names as $index => $name) {
            $response = $responses['m'.$index] ?? null;
            if ($response instanceof Response && $response->successful() && $this->declaresTools($response)) {
                $kept[] = $name;
            }
        }
        sort($kept, SORT_STRING);

        return $kept;
    }

    /**
     * @return list<string> nombres únicos dentro del alfabeto seguro
     */
    private function validNames(mixed $tags): array
    {
        if (! is_array($tags) || ! array_is_list($tags)) {
            return [];
        }

        $names = [];
        foreach ($tags as $tag) {
            $name = is_array($tag) ? ($tag['name'] ?? null) : null;
            if (is_string($name) && ModelChoice::ollama($name) !== null && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function declaresTools(Response $response): bool
    {
        $capabilities = $response->json('capabilities');

        return is_array($capabilities) && array_is_list($capabilities) && in_array('tools', $capabilities, true);
    }

    private function remaining(int|float $deadline): float
    {
        return ($deadline - hrtime(true)) / 1e9;
    }
}
