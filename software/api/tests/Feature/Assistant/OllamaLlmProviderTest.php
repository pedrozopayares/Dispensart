<?php

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// inventory-assistant «Proveedor configurable por entorno»: Ollama simulado en el borde HTTP con Http::fake (design
// D11) y enlace por AI_PROVIDER (design D2), siempre por la ruta real.

const OLLAMA_URL = 'http://ollama.prueba:11434';
const STOCK_QUESTION = '¿Cuánto stock hay de acetaminofén en la farmacia central?';

beforeEach(function () {
    freezeAlertClock();
    $this->world = assistantWorld();
});

function useOllama(): void
{
    config([
        'assistant.provider' => 'ollama',
        'assistant.ollama.base_url' => OLLAMA_URL,
        'assistant.ollama.model' => 'modelo-de-prueba',
        'assistant.ollama.timeout' => 5,
    ]);
}

function ollamaMessage(array $message): array
{
    return ['model' => 'modelo-de-prueba', 'message' => ['role' => 'assistant', 'content' => '', ...$message], 'done' => true];
}

test('Ollama con llamada a herramienta: petición con el modelo y las 4 herramientas, misma forma que con mock', function (array|string $arguments) {
    $user = worldUser($this->world, Role::AuxiliarFarmacia);
    $mockData = ask($user, STOCK_QUESTION)->assertOk()->json('data');
    useOllama();
    Http::fake([OLLAMA_URL.'/api/chat' => Http::sequence()
        ->push(ollamaMessage(['tool_calls' => [['function' => ['name' => 'get_stock', 'arguments' => $arguments]]]]))
        ->push(ollamaMessage(['content' => 'Hay 999 unidades.'])),
    ]);

    $response = ask($user, STOCK_QUESTION)->assertOk();

    // Misma forma de `data`; y como `answer` lo compone el servidor desde los datos (design D8), el mismo texto.
    $data = $response->json('data');
    expect(array_keys($data))->toBe(array_keys($mockData))
        ->and(array_keys($data['tool_calls'][0]))->toBe(array_keys($mockData['tool_calls'][0]))
        ->and([$data['outcome'], $data['tool_calls'][0]['tool'], $data['tool_calls'][0]['status']])->toBe(['answered', 'get_stock', 'ok'])
        ->and($data['answer'])->toBe($mockData['answer'])
        ->and($data['answer'])->not->toContain('999');
    $sent = Http::recorded()->map(fn (array $pair): Request => $pair[0])->values();
    expect($sent)->toHaveCount(2)
        ->and($sent[0]->url())->toBe(OLLAMA_URL.'/api/chat')
        ->and($sent[0]->method())->toBe('POST')
        ->and($sent[0]['model'])->toBe('modelo-de-prueba')
        ->and($sent[0]['stream'])->toBeFalse()
        ->and($sent[0]['options'])->toBe(['temperature' => 0])
        ->and(array_column(array_column($sent[0]['tools'], 'function'), 'name'))
        ->toBe(['find_expiring_lots', 'get_stock', 'get_low_stock_alerts', 'get_transfer_status'])
        ->and($sent[0]['messages'][0]['role'])->toBe('system')
        ->and($sent[0]['messages'][1])->toBe(['role' => 'user', 'content' => STOCK_QUESTION])
        ->and($sent[1]['messages'][3]['role'])->toBe('tool')
        ->and($sent[1]['messages'][3]['tool_name'])->toBe('get_stock')
        ->and($sent[1]['messages'][3]['content'])->toStartWith('<<<TOOL_RESULT tool="get_stock" call="c1" trust="untrusted">>>');
})->with([
    'argumentos objeto' => [['product' => 'acetaminofén', 'warehouse' => 'Farmacia Central']],
    'argumentos texto JSON' => ['{"product":"acetaminofén","warehouse":"Farmacia Central"}'],
]);

test('Ollama caído o lento: 503 assistant_unavailable sin URL, traza ni texto del proveedor', function (Closure $fake) {
    useOllama();
    Http::fake([OLLAMA_URL.'/*' => $fake]);

    $response = ask(worldUser($this->world, Role::AuxiliarFarmacia), STOCK_QUESTION);

    $response->assertStatus(503)->assertExactJson([
        'code' => 'assistant_unavailable',
        'message' => 'El asistente no está disponible en este momento. Intenta más tarde.',
    ]);
    expect($response->getContent())->not->toContain('ollama')->not->toContain('11434')->not->toContain('secreto-del-proveedor');
})->with([
    'conexión rechazada' => [fn () => Http::failedConnection('cURL error 7: Failed to connect to ollama.prueba port 11434')],
    'HTTP 500' => [fn () => fn () => Http::response(['error' => 'secreto-del-proveedor'], 500)],
    'tiempo agotado' => [fn () => Http::failedConnection('cURL error 28: Operation timed out after 5000 milliseconds')],
    'JSON sin message' => [fn () => fn () => Http::response(['error' => 'secreto-del-proveedor'], 200)],
]);

test('Sin variable usa el modo simulado: answered y ninguna petición de red', function (?string $value) {
    expect(config('assistant.provider'))->toBe('mock');
    config(['assistant.provider' => $value]);
    Http::fake();

    ask(worldUser($this->world, Role::AuxiliarFarmacia), STOCK_QUESTION)
        ->assertOk()
        ->assertJsonPath('data.outcome', 'answered');

    Http::assertNothingSent();
})->with(['nula' => [null], 'vacía' => ['']]);

test('Proveedor desconocido: 503 en el asistente y GET /api/stock sigue 200', function () {
    config(['assistant.provider' => 'openai']);
    $regente = worldUser($this->world, Role::RegenteFarmacia);

    ask($regente, STOCK_QUESTION)->assertStatus(503)->assertJsonPath('code', 'assistant_unavailable');
    $this->actingAs($regente)->getJson('/api/stock')->assertOk();
});
