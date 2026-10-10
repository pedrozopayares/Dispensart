<?php

namespace App\Providers;

use App\Services\Assistant\Llm\CatalogVocabulary;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\LlmProviderResolver;
use App\Services\Assistant\Llm\MockLlmProvider;
use App\Services\Assistant\Llm\ModelCatalog;
use App\Services\Assistant\Llm\ModelChoice;
use App\Services\Assistant\Llm\OllamaLlmProvider;
use App\Services\Assistant\Llm\UnavailableLlmProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Único lugar que conoce los proveedores concretos (design D2 de S7, D4 de S15): una sola fábrica por elección de
 * modelo, usada por el enlace perezoso por `assistant.provider` y por el resolver de la elección por pregunta.
 * Cambiar de proveedor toca esta clase y una implementación de LlmProvider, nada más.
 */
final class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, fn ($app): LlmProvider => $this->provider($app->make(ModelCatalog::class)->defaultChoice()));

        $this->app->singleton(LlmProviderResolver::class, fn ($app): LlmProviderResolver => new LlmProviderResolver(
            $app,
            fn (ModelChoice $choice): LlmProvider => $this->provider($choice),
        ));
    }

    public function boot(): void
    {
        // Limitador con nombre de la ruta del asistente, por usuario autenticado (design D12).
        RateLimiter::for('assistant', fn (Request $request): Limit => Limit::perMinute((int) config('assistant.rate_per_minute'))
            ->by('assistant:'.$request->user()?->getAuthIdentifier()));
    }

    /**
     * La URL y el plazo de Ollama salen solo del entorno; de la elección, solo el nombre ya validado.
     */
    private function provider(ModelChoice $choice): LlmProvider
    {
        return match ($choice->provider) {
            ModelChoice::MOCK => new MockLlmProvider($this->app->make(CatalogVocabulary::class)),
            ModelChoice::OLLAMA => new OllamaLlmProvider(
                (string) config('assistant.ollama.base_url'),
                $choice->name,
                (float) config('assistant.ollama.timeout'),
            ),
            // Valor desconocido: 503 solo en el asistente; nunca `mock` en silencio.
            default => new UnavailableLlmProvider,
        };
    }
}
