<?php

use App\Enums\Role;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Assistant\Evaluation\EvaluationSet;
use App\Services\Assistant\Llm\ChatResponse;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\ToolCall;
use Database\Seeders\AssistantEvalSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\RecordingLlmProvider;
use Tests\Support\ScriptedLlmProvider;

// Ayudas de las pruebas del asistente (S7). Los datos de los escenarios son los de AssistantEvalSeeder (los mismos
// que usa `assistant:eval`), sembrados en la base de pruebas dentro de la transacción de RefreshDatabase.

/**
 * Siembra el mundo de evaluación y devuelve usuarios por rol y traslados por alias.
 *
 * @return array{users: array<string, User>, transfers: array<string, int>}
 */
function assistantWorld(): array
{
    $seeder = new AssistantEvalSeeder;
    $seeder->setContainer(app());

    return $seeder->plant();
}

/**
 * Enlaza un decorador del proveedor real configurado (el `mock` por defecto) que registra cada carga.
 */
function recordProvider(): RecordingLlmProvider
{
    $recorder = new RecordingLlmProvider(app(LlmProvider::class));
    app()->instance(LlmProvider::class, $recorder);

    return $recorder;
}

/**
 * Enlaza un proveedor guionado (modelo comprometido o defectuoso).
 *
 * @param  list<ChatResponse|Closure>  $script
 */
function scriptProvider(array $script = [], ?Closure $always = null, int $safetyCap = 20): ScriptedLlmProvider
{
    $provider = new ScriptedLlmProvider($script, $always, $safetyCap);
    app()->instance(LlmProvider::class, $provider);

    return $provider;
}

/**
 * Una ronda que pide una herramienta.
 *
 * @param  array<mixed>|string  $arguments
 */
function callTool(string $name, array|string $arguments = [], string $id = 'c1'): ChatResponse
{
    return ChatResponse::toolCalls([new ToolCall($id, $name, $arguments)]);
}

function ask(User $user, string $question): TestResponse
{
    return test()->actingAs($user)->postJson('/api/assistant/ask', ['question' => $question]);
}

/**
 * Usuario del rol, de los sembrados por el mundo de evaluación.
 *
 * @param  array{users: array<string, User>, transfers: array<string, int>}  $world
 */
function worldUser(array $world, Role $role): User
{
    return $world['users'][$role->value];
}

/**
 * Foto de todo lo que una pregunta podría cambiar: "sin efectos" = misma foto antes y después.
 *
 * @return array<string, mixed>
 */
function assistantDbState(): array
{
    return [
        'stocks' => DB::table('stocks')->orderBy('id')->pluck('quantity', 'id')->all(),
        'kardex' => DB::table('kardex_movements')->count(),
        'transfers' => Transfer::query()->orderBy('id')->pluck('status', 'id')->map(fn ($s) => $s->value)->all(),
        'patient_access_logs' => DB::table('patient_access_logs')->count(),
        'audit_events' => DB::table('audit_events')->count(),
        'patients' => DB::table('patients')->count(),
    ];
}

function worldWarehouse(string $code): int
{
    return (int) DB::table('warehouses')->where('code', $code)->value('id');
}

/**
 * Copia temporal del conjunto versionado con una alteración en la entrada dada.
 */
function evaluationSetWith(Closure $alter): string
{
    $set = json_decode((string) file_get_contents(EvaluationSet::defaultPath()), true);
    $set = $alter($set);
    $path = tempnam(sys_get_temp_dir(), 'eval-set-');
    file_put_contents($path, json_encode($set, JSON_UNESCAPED_UNICODE));

    return $path;
}

// Catálogo de modelos (S15): Ollama simulado en el borde HTTP con la forma real de /api/tags y /api/show, incluidos
// los campos que la API nunca debe devolver (digest, tamaño, detalles, capacidades).

const CATALOG_OLLAMA_URL = 'http://ollama.catalogo:11434';

function useOllamaCatalog(): void
{
    config(['assistant.ollama.base_url' => CATALOG_OLLAMA_URL]);
}

/**
 * Cuerpo de /api/tags con los nombres dados.
 *
 * @param  list<string>  $names
 * @return array{models: list<array<string, mixed>>}
 */
function ollamaTags(array $names): array
{
    return ['models' => array_map(fn (string $name): array => [
        'name' => $name,
        'model' => $name,
        'modified_at' => '2026-10-01T10:00:00.000000-05:00',
        'size' => 1234567890,
        'digest' => 'sha256:'.str_repeat('a', 64),
        'details' => ['format' => 'gguf', 'family' => 'prueba', 'parameter_size' => '3B', 'quantization_level' => 'Q4_K_M'],
    ], $names)];
}

/**
 * Manejadores de Http::fake para /api/tags y /api/show.
 *
 * @param  array<string, list<string>|int>  $models  nombre → capacidades de su ficha, o código HTTP con que falla
 * @return array<string, mixed>
 */
function ollamaCatalogStubs(array $models): array
{
    return [
        CATALOG_OLLAMA_URL.'/api/tags' => Http::response(ollamaTags(array_map('strval', array_keys($models)))),
        CATALOG_OLLAMA_URL.'/api/show' => function (Request $request) use ($models) {
            $entry = $models[$request['model']] ?? 404;

            return is_int($entry)
                ? Http::response(['error' => 'model not found'], $entry)
                : Http::response([
                    'modelfile' => '# Modelfile de prueba',
                    'details' => ['family' => 'prueba', 'parameter_size' => '3B'],
                    'model_info' => ['general.architecture' => 'prueba'],
                    'capabilities' => $entry,
                    'modified_at' => '2026-10-01T10:00:00.000000-05:00',
                ]);
        },
    ];
}

/**
 * Peticiones enviadas a Ollama con la ruta dada (`/api/tags`, `/api/show`, `/api/chat`).
 *
 * @return list<Request>
 */
function ollamaRequests(string $path): array
{
    return Http::recorded()
        ->map(fn (array $pair): Request => $pair[0])
        ->filter(fn (Request $request): bool => str_ends_with($request->url(), $path))
        ->values()->all();
}

/**
 * Respuesta de /api/chat de Ollama con el mensaje dado.
 *
 * @param  array<string, mixed>  $message
 * @return array<string, mixed>
 */
function ollamaChatMessage(array $message): array
{
    return ['model' => 'modelo-simulado', 'message' => ['role' => 'assistant', 'content' => '', ...$message], 'done' => true];
}

/**
 * Pregunta con `model` en el cuerpo (cualquier tipo, para probar también los inválidos).
 */
function askWithModel(User $user, string $question, mixed $model): TestResponse
{
    return test()->actingAs($user)->postJson('/api/assistant/ask', ['question' => $question, 'model' => $model]);
}
