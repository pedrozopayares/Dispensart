<?php

use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\MockLlmProvider;
use App\Services\Assistant\Llm\ModelCatalog;
use App\Services\Assistant\Llm\ModelChoice;
use App\Services\Assistant\Llm\OllamaLlmProvider;
use Illuminate\Support\Facades\Http;

// CI › «Suite del backend aislada del entorno del anfitrión» (S17, salda D-auv-9; design D1, D2, D3 y D6).
// phpunit.xml fija AI_PROVIDER y OLLAMA_MODEL por encima del proceso: el `.env` local del stack no decide ninguna
// prueba. Se afirma en cada lector (config, getenv, catálogo, enlace del contenedor) y, aparte, el pin mismo en el
// XML: en CI la variable no existe y el valor por defecto coincide con el pin, así que solo la guarda estática lo ve.

const ISOLATION_PINS = ['AI_PROVIDER' => 'mock', 'OLLAMA_MODEL' => 'qwen2.5:3b'];

// Fuera de Feature/Assistant: el hook global no aplica aquí y ninguna prueba de este archivo sale a la red.
beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('Proveedor ollama en el entorno del proceso', function () {
    expect(config('assistant.provider'))->toBe('mock', 'config(assistant.provider) no es mock: AI_PROVIDER del proceso ganó al pin')
        ->and(getenv('AI_PROVIDER'))->toBe('mock', 'getenv(AI_PROVIDER) no es mock: los procesos hijos heredarían el proveedor del anfitrión')
        ->and(app(ModelCatalog::class)->defaultChoice()->provider)->toBe(ModelChoice::MOCK)
        ->and(app(LlmProvider::class))->toBeInstanceOf(MockLlmProvider::class);
});

test('Modelo de Ollama en el entorno del proceso', function () {
    expect(config('assistant.ollama.model'))->toBe('qwen2.5:3b', 'config(assistant.ollama.model) no es qwen2.5:3b: OLLAMA_MODEL del proceso ganó al pin')
        ->and(getenv('OLLAMA_MODEL'))->toBe('qwen2.5:3b', 'getenv(OLLAMA_MODEL) no es qwen2.5:3b: los procesos hijos heredarían el modelo del anfitrión');
});

test('Prueba que fija su propio proveedor', function () {
    // La configuración en el cuerpo corre tras el arranque y gana sobre el pin (design D6); Ollama solo en el borde HTTP.
    useOllamaCatalog();
    config(['assistant.provider' => 'ollama', 'assistant.ollama.model' => 'gemma4:e2b-mlx']);
    Http::fake(ollamaCatalogStubs(['gemma4:e2b-mlx' => ['completion', 'tools']]));

    $choice = app(ModelCatalog::class)->defaultChoice();

    expect([$choice->provider, $choice->name])->toBe([ModelChoice::OLLAMA, 'gemma4:e2b-mlx'])
        ->and(app(LlmProvider::class))->toBeInstanceOf(OllamaLlmProvider::class)
        ->and(app(ModelCatalog::class)->contains('ollama:gemma4:e2b-mlx'))->toBeTrue();
});

test('Pin retirado de la configuración de la suite', function () {
    $xml = new DOMDocument;
    expect($xml->load(base_path('phpunit.xml')))->toBeTrue('phpunit.xml no se pudo leer');
    $xpath = new DOMXPath($xml);

    foreach (ISOLATION_PINS as $name => $value) {
        // <env> necesita force: sin él PHPUnit no pisa una variable que el proceso ya trae.
        $env = $xpath->query("/phpunit/php/env[@name='{$name}' and @value='{$value}' and @force='true']");
        $server = $xpath->query("/phpunit/php/server[@name='{$name}' and @value='{$value}']");

        expect($env === false ? 0 : $env->length)->toBe(1, "phpunit.xml debe fijar <env name=\"{$name}\" value=\"{$value}\" force=\"true\"/>")
            ->and($server === false ? 0 : $server->length)->toBe(1, "phpunit.xml debe fijar <server name=\"{$name}\" value=\"{$value}\"/>");
    }
});
