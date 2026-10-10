<?php

namespace App\Services\Assistant\Llm;

use Closure;
use Illuminate\Contracts\Container\Container;

/**
 * Proveedor por pregunta (S15, design D4). Sin elección: el enlace de LlmProvider por AI_PROVIDER, el mismo que
 * sustituyen las pruebas y que usa `assistant:eval`. Con elección: la fábrica cerrada de AssistantServiceProvider,
 * único lugar que conoce las clases concretas.
 */
final readonly class LlmProviderResolver
{
    /**
     * @param  Closure(ModelChoice): LlmProvider  $factory
     */
    public function __construct(
        private Container $container,
        private Closure $factory,
    ) {}

    public function for(?ModelChoice $choice): LlmProvider
    {
        if ($choice === null) {
            return $this->container->make(LlmProvider::class);
        }

        return ($this->factory)($choice);
    }
}
