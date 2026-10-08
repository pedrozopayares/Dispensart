<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/*
| Pest (ADR-0002). Toda prueba de Feature corre contra PostgreSQL (phpunit.xml fuerza pgsql y
| dispensart_test). RefreshDatabase se declara por archivo: las pruebas de base caída no lo usan.
*/

pest()->extend(TestCase::class)->in('Feature');

// Asistente (S7): ninguna prueba sale a la red; Ollama solo se simula en el borde HTTP con Http::fake (design D15).
pest()->in('Feature/Assistant')->beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * Redirige el canal stderr a un archivo temporal para leer el log real (formateador incluido).
 * Devuelve la ruta del archivo.
 */
function captureLog(): string
{
    $path = tempnam(sys_get_temp_dir(), 'dispensart-log-');
    config(['logging.channels.stderr.handler_with.stream' => $path]);
    Log::forgetChannel('stderr');

    return $path;
}

/**
 * Líneas del log capturado, cada una decodificada como objeto JSON.
 *
 * @return list<array<string, mixed>>
 */
function logLines(string $path): array
{
    $raw = (string) file_get_contents($path);
    $lines = array_values(array_filter(explode("\n", $raw), fn (string $line): bool => $line !== ''));

    return array_map(
        fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        $lines,
    );
}

/**
 * Base inalcanzable con el driver real: puerto cerrado y conexión purgada (design D5).
 * Solo en archivos sin RefreshDatabase, para no romper la transacción de aislamiento.
 */
function makeDatabaseUnreachable(): void
{
    config(['database.connections.pgsql.port' => 1]);
    DB::purge('pgsql');
}

/**
 * Afirma que la base rechaza la escritura con el SQLSTATE dado (y la restricción nombrada, si se indica).
 * La escritura corre en un punto de guardado: el fallo no aborta la transacción de RefreshDatabase.
 */
function expectRejectedByDatabase(callable $write, string $sqlState, ?string $constraint = null): void
{
    try {
        DB::transaction(fn () => $write());
    } catch (QueryException $e) {
        expect($e->getCode())->toBe($sqlState);
        if ($constraint !== null) {
            expect($e->getMessage())->toContain($constraint);
        }

        return;
    }

    test()->fail("La base aceptó una escritura que debía rechazar (SQLSTATE {$sqlState} esperado).");
}
