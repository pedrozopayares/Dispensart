<?php

use App\Enums\Role;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Assistant\Evaluation\EvaluationSet;
use App\Services\Assistant\Llm\ChatResponse;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\ToolCall;
use Database\Seeders\AssistantEvalSeeder;
use Illuminate\Support\Facades\DB;
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
