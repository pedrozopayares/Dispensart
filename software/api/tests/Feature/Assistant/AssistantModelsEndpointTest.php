<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// GET /api/assistant/models por HTTP real (S15, design D2, D3 y D6). Ollama solo se simula en el borde con
// Http::fake; la caché del catálogo es `array` (phpunit.xml), vacía al empezar cada prueba.

const ONLY_MOCK = [['id' => 'mock', 'provider' => 'mock', 'name' => 'mock']];

beforeEach(function () {
    useOllamaCatalog();
});

function listModels(User $user): TestResponse
{
    return test()->actingAs($user)->getJson('/api/assistant/models');
}

describe('Lista de modelos disponibles', function () {
    test('Modelos con Ollama disponible: mock primero y luego el modelo con herramientas', function () {
        Http::fake(ollamaCatalogStubs(['gemma4:e2b-mlx' => ['completion', 'tools']]));

        listModels(User::factory()->auxiliar()->create())
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => 'mock', 'provider' => 'mock', 'name' => 'mock'],
                ['id' => 'ollama:gemma4:e2b-mlx', 'provider' => 'ollama', 'name' => 'gemma4:e2b-mlx'],
            ]]);
        // Control: la lista salió de Ollama simulado (una consulta de etiquetas y una ficha).
        expect(ollamaRequests('/api/tags'))->toHaveCount(1)
            ->and(ollamaRequests('/api/show'))->toHaveCount(1)
            ->and(ollamaRequests('/api/show')[0]['model'])->toBe('gemma4:e2b-mlx');
    });

    test('Respuesta sin datos del servidor: sin URL de Ollama, digest, tamaño, fechas, detalles ni capacidades', function () {
        Http::fake(ollamaCatalogStubs(['gemma4:e2b-mlx' => ['completion', 'tools']]));

        $body = (string) listModels(User::factory()->auxiliar()->create())->assertOk()->getContent();

        // Control positivo: el cuerpo sí trae el modelo, de modo que el barrido lee una respuesta real.
        expect($body)->toContain('gemma4:e2b-mlx');
        foreach ([CATALOG_OLLAMA_URL, 'ollama.catalogo', '11434', 'digest', 'size', 'modified_at', 'details', 'capabilities', 'sha256', 'completion', 'tools'] as $forbidden) {
            expect($body)->not->toContain($forbidden);
        }
    });

    test('Lista sin sesión: 401 unauthenticated y ninguna petición a Ollama', function () {
        Http::fake(ollamaCatalogStubs(['gemma4:e2b-mlx' => ['tools']]));
        $spa = new SpaClient($this);
        $spa->csrfCookie();

        $spa->get('/api/assistant/models')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');

        Http::assertNothingSent();
    });

    test('Lista con proveedor por defecto desconocido: con AI_PROVIDER openai y Ollama sin responder, solo mock', function () {
        config(['assistant.provider' => 'openai']);
        Http::fake([CATALOG_OLLAMA_URL.'/*' => Http::failedConnection('cURL error 7: Failed to connect')]);

        listModels(User::factory()->regente()->create())->assertOk()->assertExactJson(['data' => ONLY_MOCK]);
        // Control: sí se consultó a Ollama (la lista no se armó sin mirar).
        expect(ollamaRequests('/api/tags'))->toHaveCount(1);
    });

    test('Lista sin efectos en la base: misma foto con Ollama disponible y con Ollama caído', function () {
        freezeAlertClock();
        assistantWorld();
        $ollamaUp = true;
        Http::fake(function (Request $request) use (&$ollamaUp) {
            if (! $ollamaUp) {
                return Http::failedConnection('cURL error 7: Failed to connect');
            }

            return str_ends_with($request->url(), '/api/tags')
                ? Http::response(ollamaTags(['gemma4:e2b-mlx']))
                : Http::response(['capabilities' => ['completion', 'tools']]);
        });
        $snapshot = fn (): array => [...assistantDbState(), 'users' => DB::table('users')->count()];
        $auditor = User::factory()->auditor()->create();
        $before = $snapshot();

        listModels($auditor)->assertOk()->assertJsonCount(2, 'data');
        // Dos estados de Ollama en una prueba: se vacía el almacén del catálogo entre ambos (design D10).
        Cache::store(config('assistant.models.cache_store'))->flush();
        $ollamaUp = false;
        listModels($auditor)->assertOk()->assertExactJson(['data' => ONLY_MOCK]);

        expect($snapshot())->toBe($before)
            ->and($before['stocks'])->not->toBe([]);
    });
});

describe('Disponibilidad de modelos de Ollama', function () {
    test('Modelo sin herramientas omitido: el modelo de solo embedding no entra a la lista', function () {
        Http::fake(ollamaCatalogStubs([
            'gemma4:e2b-mlx' => ['completion', 'tools'],
            'nomic-embed-text' => ['embedding'],
        ]));

        $ids = array_column(listModels(User::factory()->auxiliar()->create())->assertOk()->json('data'), 'id');

        expect($ids)->toBe(['mock', 'ollama:gemma4:e2b-mlx'])
            ->and($ids)->not->toContain('ollama:nomic-embed-text')
            // Control: la ficha del modelo omitido sí se consultó.
            ->and(array_map(fn (Request $request): mixed => $request['model'], ollamaRequests('/api/show')))
            ->toEqualCanonicalizing(['gemma4:e2b-mlx', 'nomic-embed-text']);
    });

    test('Ollama caído: 200 solo con mock y sin code ni message de error', function () {
        expect(config('assistant.models.cache_store'))->toBe('array');
        Http::fake([CATALOG_OLLAMA_URL.'/*' => Http::failedConnection('cURL error 7: Failed to connect to ollama.catalogo port 11434')]);

        $response = listModels(User::factory()->auxiliar()->create())->assertOk()->assertExactJson(['data' => ONLY_MOCK]);

        expect($response->json())->not->toHaveKey('code')->not->toHaveKey('message')
            ->and(ollamaRequests('/api/tags'))->toHaveCount(1)
            ->and(ollamaRequests('/api/show'))->toBe([]);
    });

    test('Ollama lento: solo mock, sin consultar fichas y con plazo de red dentro del presupuesto', function () {
        config(['assistant.models.budget_seconds' => 0.2]);
        $tagsOptions = [];
        Http::fake([
            CATALOG_OLLAMA_URL.'/api/tags' => function (Request $request, array $options) use (&$tagsOptions) {
                $tagsOptions = $options;
                usleep(300_000);

                return Http::response(ollamaTags(['gemma4:e2b-mlx']));
            },
            CATALOG_OLLAMA_URL.'/api/show' => Http::response(['capabilities' => ['completion', 'tools']]),
        ]);

        listModels(User::factory()->auxiliar()->create())->assertOk()->assertExactJson(['data' => ONLY_MOCK]);

        expect(ollamaRequests('/api/show'))->toBe([])
            ->and($tagsOptions['timeout'])->toBeGreaterThan(0)->toBeLessThanOrEqual(0.2)
            ->and($tagsOptions['connect_timeout'])->toBeGreaterThan(0)->toBeLessThanOrEqual(0.2);
    });

    test('Ollama con error o respuesta mal formada: 200 solo con mock', function (Closure $tags) {
        Http::fake([
            CATALOG_OLLAMA_URL.'/api/tags' => $tags(),
            CATALOG_OLLAMA_URL.'/api/show' => Http::response(['capabilities' => ['completion', 'tools']]),
        ]);

        listModels(User::factory()->auxiliar()->create())->assertOk()->assertExactJson(['data' => ONLY_MOCK]);

        expect(ollamaRequests('/api/tags'))->toHaveCount(1)
            ->and(ollamaRequests('/api/show'))->toBe([]);
    })->with([
        // Con una lista válida en el cuerpo: el código de error manda, no el cuerpo.
        'HTTP 500' => [fn () => Http::response(ollamaTags(['gemma4:e2b-mlx']), 500)],
        'JSON sin models' => [fn () => Http::response(['error' => 'sin lista', 'modelos' => ['gemma4:e2b-mlx']])],
        'models que no es lista' => [fn () => Http::response(['models' => 'gemma4:e2b-mlx'])],
        'texto no JSON' => [fn () => Http::response('<html>gemma4:e2b-mlx</html>', 200, ['Content-Type' => 'text/html'])],
    ]);

    test('Ficha de un modelo que falla: se omite sin afectar a los demás', function () {
        Http::fake(ollamaCatalogStubs([
            'qwen2.5:3b' => 404,
            'gemma4:e2b-mlx' => ['completion', 'tools'],
        ]));

        listModels(User::factory()->auxiliar()->create())->assertOk()->assertExactJson(['data' => [
            ['id' => 'mock', 'provider' => 'mock', 'name' => 'mock'],
            ['id' => 'ollama:gemma4:e2b-mlx', 'provider' => 'ollama', 'name' => 'gemma4:e2b-mlx'],
        ]]);
        expect(ollamaRequests('/api/show'))->toHaveCount(2);
    });

    test('Ollama sin modelos descargados: solo mock y ninguna consulta de fichas', function () {
        Http::fake(ollamaCatalogStubs([]));

        listModels(User::factory()->auxiliar()->create())->assertOk()->assertExactJson(['data' => ONLY_MOCK]);

        expect(ollamaRequests('/api/tags'))->toHaveCount(1)
            ->and(ollamaRequests('/api/show'))->toBe([]);
    });

    test('nombres fuera del alfabeto seguro no entran a la lista y la lista sale ordenada por nombre', function () {
        Http::fake(ollamaCatalogStubs([
            'zeta:latest' => ['tools'],
            "malo\nX-Inyectado: 1" => ['tools'],
            '<script>' => ['tools'],
            'hf.co/org/repo:Q4_K_M' => ['completion', 'tools'],
            'alfa:1b' => ['tools'],
        ]));

        $ids = array_column(listModels(User::factory()->auxiliar()->create())->assertOk()->json('data'), 'id');

        expect($ids)->toBe(['mock', 'ollama:alfa:1b', 'ollama:hf.co/org/repo:Q4_K_M', 'ollama:zeta:latest'])
            // Las fichas solo se piden para nombres válidos.
            ->and(array_map(fn (Request $request): mixed => $request['model'], ollamaRequests('/api/show')))
            ->toEqualCanonicalizing(['zeta:latest', 'hf.co/org/repo:Q4_K_M', 'alfa:1b']);
    });
});
