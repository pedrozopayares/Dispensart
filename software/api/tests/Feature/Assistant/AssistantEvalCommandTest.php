<?php

use App\Actions\Inventory\AdjustStock;
use App\Actions\Transfers\CreateTransfer;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Assistant\Evaluation\EvaluationDatabase;
use App\Services\Assistant\Evaluation\EvaluationSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

// assistant-evaluation «Comando que reporta aciertos» y «Evaluación aislada de los datos operativos» (design D14):
// `assistant:eval` real, con el `mock` real, sobre su base desechable `dispensart_test_assistant_eval`.

/**
 * @return array{0: int, 1: string}
 */
function runEval(array $options = []): array
{
    // Búfer propio: el `migrate` anidado del comando reemplaza Artisan::output().
    $buffer = new BufferedOutput;
    $exit = Artisan::call('assistant:eval', $options, $buffer);

    return [$exit, $buffer->fetch()];
}

function entryCount(): int
{
    return count(json_decode((string) file_get_contents(EvaluationSet::defaultPath()), true)['entries']);
}

/**
 * Conteos de la base operativa que el comando no debe tocar.
 *
 * @return array<string, int>
 */
function operationalCounts(): array
{
    return collect(['stocks', 'kardex_movements', 'transfers', 'patients', 'patient_access_logs', 'audit_events', 'users', 'lots'])
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
}

function evalDatabaseExists(): bool
{
    return DB::table('pg_database')->where('datname', DB::connection()->getDatabaseName().EvaluationDatabase::SUFFIX)->exists();
}

test('Todas aciertan con el modo simulado', function () {
    [$exit, $output] = runEval();
    $total = entryCount();

    expect($exit)->toBe(0)
        ->and(substr_count($output, 'ACIERTO'))->toBe($total)
        ->and($output)->not->toContain('FALLO')
        ->and($output)->toContain("Aciertos: {$total}/{$total}")
        ->and(evalDatabaseExists())->toBeFalse();
});

test('Una respuesta equivocada cuenta como fallo', function () {
    $path = evaluationSetWith(function (array $set): array {
        $set['entries'][5]['expect']['answer_contains'] = ['999 unidades disponibles'];

        return $set;
    });
    $total = entryCount();

    [$exit, $output] = runEval(['--file' => $path]);

    expect($exit)->toBe(1)
        ->and(substr_count($output, 'FALLO'))->toBe(1)
        ->and($output)->toMatch('/stock-central\s+\|\s+auxiliar_farmacia\s+\|\s+FALLO\s+\|\s+answer no contiene «999 unidades disponibles»/')
        ->and($output)->toContain('Aciertos: '.($total - 1)."/{$total}");
});

test('Archivo ausente o mal formado', function (string $case, string $message) {
    $path = sys_get_temp_dir().'/eval-set-'.uniqid().'.json';
    if ($case === 'json') {
        file_put_contents($path, '{"entries": [');
    }
    expect(is_file($path))->toBe($case === 'json');

    [$exit, $output] = runEval(['--file' => $path]);

    expect($exit)->toBe(2)
        ->and($output)->toContain($message)
        ->and($output)->not->toContain('Aciertos')->not->toContain('0/0')
        ->and($output)->not->toContain('Exception')->not->toContain('#0 ');
})->with([
    'ausente' => ['missing', 'No se encontró el conjunto de evaluación'],
    'JSON inválido' => ['json', 'El conjunto de evaluación no es JSON válido.'],
]);

test('Proveedor no disponible', function () {
    config(['assistant.provider' => 'ollama', 'assistant.ollama.base_url' => 'http://ollama.prueba:11434']);
    Http::fake(['http://ollama.prueba:11434/*' => Http::failedConnection()]);

    [$exit, $output] = runEval();
    preg_match_all('/\|\s+(ACIERTO|FALLO)\s+\|\s+([^|]*?)\s+\|$/m', $output, $rows, PREG_SET_ORDER);
    $failures = array_filter($rows, fn (array $row): bool => $row[1] === 'FALLO');

    expect($exit)->toBe(1)
        ->and($rows)->toHaveCount(entryCount())
        // Toda fila que llega al proveedor falla por asistente no disponible; las que el filtro previo responde sin
        // proveedor (preguntas de pacientes) no lo necesitan (journal: desvío de redacción del escenario).
        ->and(array_unique(array_column($failures, 2)))->toBe(['asistente no disponible'])
        ->and(count($failures))->toBe(entryCount() - 2)
        ->and(evalDatabaseExists())->toBeFalse();
});

test('Sin cambios persistentes', function () {
    $this->seed();
    $before = operationalCounts();
    expect($before['stocks'])->toBeGreaterThan(0)->and($before['patients'])->toBeGreaterThan(0);

    [$exit] = runEval();

    expect($exit)->toBe(0)
        ->and(operationalCounts())->toBe($before)
        ->and(evalDatabaseExists())->toBeFalse();
});

test('Datos operativos alterados', function () {
    $this->seed();
    $regente = User::query()->where('email', 'regente@dispensart.test')->firstOrFail();
    $stock = Stock::query()->whereHas('lot', fn ($q) => $q->where('lot_code', 'L-ACE-2402'))
        ->where('warehouse_id', Warehouse::query()->where('code', 'FC')->value('id'))->firstOrFail();
    app(AdjustStock::class)->handle($regente, adjustmentBody($stock, -15, 'Conteo físico de prueba'));
    app(CreateTransfer::class)->handle(User::query()->where('email', 'auxiliar@dispensart.test')->firstOrFail(), [
        'origin_warehouse_id' => $stock->warehouse_id,
        'destination_warehouse_id' => Warehouse::query()->where('code', 'FU')->value('id'),
        'notes' => 'Traslado operativo previo a la evaluación',
        'lines' => [['lot_id' => Lot::query()->where('lot_code', 'L-ACE-2403')->value('id'), 'quantity' => 2]],
    ]);

    [$exit, $output] = runEval();

    expect($exit)->toBe(0)
        ->and($output)->not->toContain('FALLO')
        ->and($output)->toContain('Aciertos: '.entryCount().'/'.entryCount());
});

test('Base de evaluación no creable', function (string $case, string $message) {
    $this->seed();
    $before = operationalCounts();
    if ($case === 'no_createdb') {
        // La conexión de la prueba ya está abierta; la de administración se clona con este usuario inexistente.
        config(['database.connections.pgsql.username' => 'rol_sin_createdb', 'database.connections.pgsql.password' => 'x']);
    } else {
        app()->instance(EvaluationDatabase::class, new EvaluationDatabase(DB::connection()->getDatabaseName()));
    }

    [$exit, $output] = runEval();

    expect($exit)->toBe(2)
        ->and($output)->toContain($message)
        ->and($output)->not->toContain('Aciertos')->not->toContain('ACIERTO')
        ->and(operationalCounts())->toBe($before);
})->with([
    'usuario sin acceso para crearla' => ['no_createdb', 'No se pudo preparar la base de evaluación desechable'],
    'nombre igual al de la base operativa' => ['name_clash', 'La base de evaluación coincide con la base operativa: no se evalúa.'],
]);
