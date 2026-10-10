<?php

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// inventory-assistant «Modelo elegido por pregunta» (S15, design D3, D4 y D5) por POST /api/assistant/ask real.
// Ollama solo en el borde HTTP con Http::fake. Proveedor y modelo por defecto se fijan aquí: el `.env` local del
// stack no decide ninguna prueba.

const CHOICE_QUESTION = '¿Cuánto stock hay de acetaminofén en la farmacia central?';
const MODEL_UNAVAILABLE = 'El modelo elegido no está disponible.';

beforeEach(function () {
    freezeAlertClock();
    $this->world = assistantWorld();
    useOllamaCatalog();
    config(['assistant.provider' => 'mock', 'assistant.ollama.model' => 'qwen2.5:3b', 'assistant.ollama.timeout' => 5]);
});

/**
 * Catálogo con `gemma4:e2b-mlx` (con herramientas) y un /api/chat que pide get_stock y luego responde texto.
 *
 * @param  array<string, list<string>|int>  $models
 * @return array<string, mixed>
 */
function catalogAndChat(array $models = ['gemma4:e2b-mlx' => ['completion', 'tools']]): array
{
    return [
        ...ollamaCatalogStubs($models),
        CATALOG_OLLAMA_URL.'/api/chat' => Http::sequence()
            ->push(ollamaChatMessage(['tool_calls' => [['function' => ['name' => 'get_stock', 'arguments' => ['product' => 'acetaminofén', 'warehouse' => 'Farmacia Central']]]]]))
            ->push(ollamaChatMessage(['content' => 'Hay 999 unidades.']))
            ->push(ollamaChatMessage(['tool_calls' => [['function' => ['name' => 'get_stock', 'arguments' => ['product' => 'acetaminofén', 'warehouse' => 'Farmacia Central']]]]]))
            ->push(ollamaChatMessage(['content' => 'Hay 999 unidades.'])),
    ];
}

function expectModelRejected(TestResponse $response): void
{
    $response->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.model', [MODEL_UNAVAILABLE]);
}

describe('Modelo elegido por pregunta', function () {
    test('Pregunta con un modelo de Ollama disponible: /api/chat con ese modelo y data.model', function () {
        $user = worldUser($this->world, Role::AuxiliarFarmacia);
        $mockData = ask($user, CHOICE_QUESTION)->assertOk()->json('data');
        Http::fake(catalogAndChat());

        $response = askWithModel($user, CHOICE_QUESTION, 'ollama:gemma4:e2b-mlx')->assertOk();

        $chat = ollamaRequests('/api/chat');
        expect($chat)->toHaveCount(2)
            ->and($chat[0]->url())->toBe(CATALOG_OLLAMA_URL.'/api/chat')
            ->and($chat[0]['model'])->toBe('gemma4:e2b-mlx')
            ->and(array_column(array_column($chat[0]['tools'], 'function'), 'name'))
            ->toBe(['find_expiring_lots', 'get_stock', 'get_low_stock_alerts', 'get_transfer_status']);
        $data = $response->json('data');
        expect($data['model'])->toBe('ollama:gemma4:e2b-mlx')
            ->and(array_keys($data))->toBe(array_keys($mockData))
            ->and($mockData['model'])->toBe('mock')
            ->and([$data['outcome'], $data['answer']])->toBe([$mockData['outcome'], $mockData['answer']]);
    });

    test('Pregunta con un modelo de Ollama disponible tras pedir la lista: una sola consulta de etiquetas (caché)', function () {
        $user = worldUser($this->world, Role::AuxiliarFarmacia);
        Http::fake(catalogAndChat());

        $this->actingAs($user)->getJson('/api/assistant/models')->assertOk()->assertJsonPath('data.1.id', 'ollama:gemma4:e2b-mlx');
        askWithModel($user, CHOICE_QUESTION, 'ollama:gemma4:e2b-mlx')->assertOk()->assertJsonPath('data.model', 'ollama:gemma4:e2b-mlx');

        expect(ollamaRequests('/api/tags'))->toHaveCount(1)
            ->and(ollamaRequests('/api/show'))->toHaveCount(1)
            // Control: la pregunta sí llegó a Ollama.
            ->and(ollamaRequests('/api/chat'))->toHaveCount(2);
    });

    test('Modelo simulado sin red: con AI_PROVIDER ollama, model mock responde sin ninguna petición', function () {
        config(['assistant.provider' => 'ollama']);
        Http::fake();

        askWithModel(worldUser($this->world, Role::RegenteFarmacia), CHOICE_QUESTION, 'mock')
            ->assertOk()
            ->assertJsonPath('data.outcome', 'answered')
            ->assertJsonPath('data.model', 'mock');

        Http::assertNothingSent();
    });

    test('Sin modelo usa el valor por defecto: mock sin red y luego Ollama con OLLAMA_MODEL', function () {
        $user = worldUser($this->world, Role::AuxiliarFarmacia);
        Http::fake(catalogAndChat());

        ask($user, CHOICE_QUESTION)->assertOk()->assertJsonPath('data.model', 'mock');
        Http::assertNothingSent();

        config(['assistant.provider' => 'ollama']);
        ask($user, CHOICE_QUESTION)->assertOk()->assertJsonPath('data.model', 'ollama:qwen2.5:3b');

        $chat = ollamaRequests('/api/chat');
        expect($chat)->toHaveCount(2)
            ->and($chat[0]['model'])->toBe('qwen2.5:3b')
            // Sin `model` no hay validación contra el catálogo: ninguna consulta de etiquetas.
            ->and(ollamaRequests('/api/tags'))->toBe([]);
    });

    test('Modelo fuera de la lista: 422 errors.model y nada sale salvo, si acaso, el catálogo de OLLAMA_BASE_URL', function (string $model, int $tagRequests) {
        Http::fake(catalogAndChat());

        expectModelRejected(askWithModel(worldUser($this->world, Role::AuxiliarFarmacia), CHOICE_QUESTION, $model));

        expect(ollamaRequests('/api/chat'))->toBe([])
            ->and(ollamaRequests('/api/tags'))->toHaveCount($tagRequests)
            ->and(Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->reject(
                fn (string $url): bool => str_starts_with($url, CATALOG_OLLAMA_URL.'/'),
            )->all())->toBe([]);
    })->with([
        'modelo de Ollama inexistente' => ['ollama:modelo-inexistente', 1],
        'otro proveedor' => ['openai:gpt-4o', 0],
        'una URL' => ['http://atacante.example/api', 0],
    ]);

    test('Modelo descargado sin herramientas: 422 errors.model y /api/chat sin llamadas', function () {
        Http::fake(catalogAndChat(['nomic-embed-text' => ['embedding']]));

        expectModelRejected(askWithModel(worldUser($this->world, Role::Auditor), CHOICE_QUESTION, 'ollama:nomic-embed-text'));

        expect(ollamaRequests('/api/chat'))->toBe([])
            // Control: la ficha sí se consultó; el rechazo viene de su capacidad, no de no mirar.
            ->and(ollamaRequests('/api/show'))->toHaveCount(1);
    });

    test('Modelo elegido con Ollama caído: 422 errors.model, no 503', function () {
        Http::fake([CATALOG_OLLAMA_URL.'/*' => Http::failedConnection('cURL error 7: Failed to connect')]);

        expectModelRejected(askWithModel(worldUser($this->world, Role::AuxiliarFarmacia), CHOICE_QUESTION, 'ollama:gemma4:e2b-mlx'));

        expect(ollamaRequests('/api/tags'))->toHaveCount(1)
            ->and(ollamaRequests('/api/chat'))->toBe([]);
    });

    test('Modelo con tipo inválido: 422 errors.model sin llamar al proveedor ni a la red', function (mixed $model) {
        $recorder = recordProvider();
        Http::fake();

        askWithModel(worldUser($this->world, Role::RegenteFarmacia), CHOICE_QUESTION, $model)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['model']]);

        expect($recorder->requests)->toBe([]);
        Http::assertNothingSent();
    })->with([
        'numérico' => [7],
        'lista' => [['mock']],
        'nulo' => [null],
    ]);

    test('Ollama falla al responder con el modelo elegido: 503 assistant_unavailable con el mensaje fijo', function () {
        Http::fake([
            ...ollamaCatalogStubs(['gemma4:e2b-mlx' => ['completion', 'tools']]),
            CATALOG_OLLAMA_URL.'/api/chat' => Http::response(['error' => 'secreto-del-proveedor'], 500),
        ]);

        $response = askWithModel(worldUser($this->world, Role::AuxiliarFarmacia), CHOICE_QUESTION, 'ollama:gemma4:e2b-mlx');

        $response->assertStatus(503)->assertExactJson([
            'code' => 'assistant_unavailable',
            'message' => 'El asistente no está disponible en este momento. Intenta más tarde.',
        ]);
        expect(ollamaRequests('/api/chat'))->toHaveCount(1);
    });

    test('Pregunta sobre un paciente con un modelo de Ollama: out_of_scope, data.model y nada de la pregunta hacia Ollama', function () {
        Http::fake(catalogAndChat());
        $question = '¿Qué medicamentos le dispensaron a la paciente Ana Sintética Pérez?';

        askWithModel(worldUser($this->world, Role::RegenteFarmacia), $question, 'ollama:gemma4:e2b-mlx')
            ->assertOk()
            ->assertJsonPath('data.outcome', 'out_of_scope')
            ->assertJsonPath('data.model', 'ollama:gemma4:e2b-mlx');

        $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->url().' '.$pair[0]->body())->implode("\n");
        expect(ollamaRequests('/api/chat'))->toBe([])
            // Control: el modelo se validó contra Ollama, así que el barrido lee peticiones reales.
            ->and(ollamaRequests('/api/tags'))->toHaveCount(1);
        foreach (['Ana', 'Sint', 'Pérez', 'paciente', 'medicamentos'] as $forbidden) {
            expect($sent)->not->toContain($forbidden);
        }
    });

    test('El servidor no recuerda la elección: la pregunta siguiente sin model usa mock', function () {
        $user = worldUser($this->world, Role::AuxiliarFarmacia);
        Http::fake(catalogAndChat());

        askWithModel($user, CHOICE_QUESTION, 'ollama:gemma4:e2b-mlx')->assertOk()->assertJsonPath('data.model', 'ollama:gemma4:e2b-mlx');
        $chatBefore = count(ollamaRequests('/api/chat'));
        ask($user, CHOICE_QUESTION)->assertOk()->assertJsonPath('data.model', 'mock');

        expect($chatBefore)->toBe(2)
            ->and(ollamaRequests('/api/chat'))->toHaveCount($chatBefore);
    });
});

test('Http::fake registra la URL de cada petición enviada (control del barrido de destinos)', function () {
    Http::fake();
    Http::get('http://atacante.example/api');

    expect(Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all())->toBe(['http://atacante.example/api'])
        ->and(Http::recorded()->first()[0])->toBeInstanceOf(Request::class);
});
