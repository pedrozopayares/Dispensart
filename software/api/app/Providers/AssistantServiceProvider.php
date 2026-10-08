<?php

namespace App\Providers;

use App\Services\Assistant\Llm\CatalogVocabulary;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\MockLlmProvider;
use App\Services\Assistant\Llm\OllamaLlmProvider;
use App\Services\Assistant\Llm\UnavailableLlmProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Único lugar que conoce los proveedores concretos (design D2): enlace perezoso por `assistant.provider`.
 * Cambiar de proveedor toca esta clase y una implementación de LlmProvider, nada más.
 */
final class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, function ($app): LlmProvider {
            $provider = config('assistant.provider');

            return match (is_string($provider) && $provider !== '' ? $provider : 'mock') {
                'mock' => new MockLlmProvider($app->make(CatalogVocabulary::class)),
                'ollama' => new OllamaLlmProvider(
                    (string) config('assistant.ollama.base_url'),
                    (string) config('assistant.ollama.model'),
                    (float) config('assistant.ollama.timeout'),
                ),
                // Valor desconocido: 503 solo en el asistente; nunca `mock` en silencio.
                default => new UnavailableLlmProvider,
            };
        });
    }

    public function boot(): void
    {
        // Limitador con nombre de la ruta del asistente, por usuario autenticado (design D12).
        RateLimiter::for('assistant', fn (Request $request): Limit => Limit::perMinute((int) config('assistant.rate_per_minute'))
            ->by('assistant:'.$request->user()?->getAuthIdentifier()));
    }
}
