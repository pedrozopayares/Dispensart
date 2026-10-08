<?php

/*
| Worker de la prueba de carrera (design D8). Un proceso PHP = una conexión propia a PostgreSQL.
| Arranca la app, se identifica como race-worker en pg_stat_activity, autentica al usuario indicado y
| despacha POST /api/stock-adjustments por el kernel HTTP real (FormRequest, Policy, acción, libro, render).
| Imprime {status, code} en una línea. Solo lo lanza tests/Support/RaceRunner; nunca forma parte de la app.
*/

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\Support\RaceRunner;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';

/** @var array{user_id: int, body: array<string, mixed>} $payload */
$payload = json_decode($argv[1] ?? '', true, flags: JSON_THROW_ON_ERROR);

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Salvaguarda: solo bases de prueba, nunca la de desarrollo.
$database = (string) getenv('DB_DATABASE');
if (! str_starts_with($database, 'dispensart_test')) {
    fwrite(STDERR, "race-worker: base no permitida: {$database}\n");
    exit(2);
}
config([
    'database.connections.pgsql.database' => $database,
    'database.connections.pgsql.application_name' => RaceRunner::APPLICATION_NAME,
]);

$request = Request::create(
    '/api/stock-adjustments',
    'POST',
    server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
    content: (string) json_encode($payload['body']),
);
$app->instance('request', $request);

// Como actingAs(): el guardia web lleva al usuario; Sanctum lo toma de ahí.
$auth = $app->make('auth');
$auth->guard('web')->setUser(User::query()->findOrFail($payload['user_id']));
$auth->shouldUse('web');

$response = $kernel->handle($request);
$kernel->terminate($request, $response);

/** @var array{code?: string}|null $body */
$body = json_decode((string) $response->getContent(), true);
fwrite(STDOUT, (string) json_encode(['status' => $response->getStatusCode(), 'code' => $body['code'] ?? null]));
