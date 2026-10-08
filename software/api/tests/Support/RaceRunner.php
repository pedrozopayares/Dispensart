<?php

namespace Tests\Support;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Carrera real entre peticiones HTTP (design D8): cada petición corre en su propio proceso PHP
 * (tests/Support/race-worker.php) con su propia conexión a PostgreSQL y el kernel HTTP completo.
 *
 * Barrera determinista: antes de lanzar, una conexión aparte toma LOCK TABLE kardex_movements IN SHARE MODE.
 * Ningún worker puede insertar su movimiento hasta que todos estén esperando un bloqueo (visible en
 * pg_stat_activity); recién entonces se suelta. Así las transacciones se solapan siempre, no "a veces".
 */
final class RaceRunner
{
    public const APPLICATION_NAME = 'race-worker';

    private const BARRIER_CONNECTION = 'race_barrier';

    private const BARRIER_TIMEOUT_SECONDS = 15;

    /**
     * Envía en paralelo POST /api/stock-adjustments, uno por petición, y devuelve {status, code} de cada una
     * en el mismo orden.
     *
     * @param  list<array{user_id: int, body: array<string, mixed>}>  $requests
     * @return list<array{status: int, code: string|null}>
     */
    public static function postAdjustments(array $requests): array
    {
        $barrier = self::barrierConnection();
        $barrier->beginTransaction();
        $barrier->statement('LOCK TABLE kardex_movements IN SHARE MODE');

        $processes = [];
        try {
            foreach ($requests as $request) {
                $processes[] = self::startWorker($request);
            }
            self::waitUntilAllBlocked($processes);
        } finally {
            $barrier->rollBack();
            DB::purge(self::BARRIER_CONNECTION);
        }

        return array_map(self::result(...), $processes);
    }

    private static function barrierConnection(): Connection
    {
        config(['database.connections.'.self::BARRIER_CONNECTION => config('database.connections.'.config('database.default'))]);

        /** @var Connection */
        return DB::connection(self::BARRIER_CONNECTION);
    }

    /**
     * @param  array{user_id: int, body: array<string, mixed>}  $request
     */
    private static function startWorker(array $request): InvokedProcess
    {
        return Process::path(base_path())
            ->env([
                // Nombre real de la base de esta prueba (compatible con --parallel); el worker lo exige.
                'DB_DATABASE' => DB::connection()->getDatabaseName(),
                'LOG_STDERR_STREAM' => 'php://stderr',
            ])
            ->timeout(60)
            ->start([PHP_BINARY, 'tests/Support/race-worker.php', (string) json_encode($request)]);
    }

    /**
     * @param  list<InvokedProcess>  $processes
     */
    private static function waitUntilAllBlocked(array $processes): void
    {
        $deadline = microtime(true) + self::BARRIER_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $blocked = (int) DB::scalar(
                "SELECT count(*) FROM pg_stat_activity
                 WHERE datname = current_database() AND application_name = ? AND wait_event_type = 'Lock'",
                [self::APPLICATION_NAME],
            );
            if ($blocked === count($processes)) {
                return;
            }

            foreach ($processes as $process) {
                if (! $process->running()) {
                    self::fail('un worker terminó antes de formar la barrera', $processes);
                }
            }

            usleep(20_000);
        }

        self::fail('la barrera no se formó en '.self::BARRIER_TIMEOUT_SECONDS.' s', $processes);
    }

    /**
     * @return array{status: int, code: string|null}
     */
    private static function result(InvokedProcess $process): array
    {
        $result = $process->wait();
        /** @var array{status: int, code: string|null}|null $decoded */
        $decoded = json_decode(trim($result->output()), true);

        if (! $result->successful() || $decoded === null) {
            throw new RuntimeException('Worker de carrera falló: '.$result->errorOutput().$result->output());
        }

        return $decoded;
    }

    /**
     * @param  list<InvokedProcess>  $processes
     */
    private static function fail(string $reason, array $processes): never
    {
        $stderr = '';
        foreach ($processes as $process) {
            if ($process->running()) {
                $process->signal(SIGTERM);
            }
            $stderr .= "\n--- worker ---\n".$process->wait()->errorOutput();
        }

        throw new RuntimeException("Carrera inválida: {$reason}.{$stderr}");
    }
}
