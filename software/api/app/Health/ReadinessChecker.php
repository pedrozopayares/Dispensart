<?php

namespace App\Health;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Disponibilidad = la base responde a una consulta y no quedan migraciones pendientes (design D5).
 * Las excepciones se registran en el log (con correlation_id) y nunca llegan a la respuesta.
 */
final class ReadinessChecker
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Migrator $migrator,
    ) {}

    public function check(): ReadinessResult
    {
        try {
            $this->database->connection()->select('select 1');
        } catch (Throwable $exception) {
            report($exception);

            return new ReadinessResult(ReadinessResult::FAIL, ReadinessResult::SKIPPED);
        }

        try {
            $migrations = $this->hasPendingMigrations() ? ReadinessResult::PENDING : ReadinessResult::OK;
        } catch (Throwable $exception) {
            report($exception);
            $migrations = ReadinessResult::FAIL;
        }

        return new ReadinessResult(ReadinessResult::OK, $migrations);
    }

    private function hasPendingMigrations(): bool
    {
        if (! $this->migrator->repositoryExists()) {
            return true;
        }

        $files = $this->migrator->getMigrationFiles([
            database_path('migrations'),
            ...$this->migrator->paths(),
        ]);

        $ran = $this->migrator->getRepository()->getRan();

        return array_diff(array_keys($files), $ran) !== [];
    }
}
