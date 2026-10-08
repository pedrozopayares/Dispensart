<?php

namespace App\Services\Assistant\Evaluation;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

/**
 * Base desechable de `assistant:eval` (assistant-evaluation «Evaluación aislada de los datos operativos»; design
 * D14). Nombre derivado y no configurable: `{base operativa}_assistant_eval`. Por corrida: conexión de
 * administración propia (fuera de toda transacción) → DROP IF EXISTS … WITH (FORCE) + CREATE → migraciones →
 * conexión por defecto apuntada a la base de evaluación → evaluar → en `finally`: conexión restaurada y DROP.
 * Nada se escribe en la base operativa. Requiere privilegio CREATEDB.
 */
final class EvaluationDatabase
{
    public const SUFFIX = '_assistant_eval';

    public const CONNECTION = 'assistant_eval';

    private const ADMIN_CONNECTION = 'assistant_eval_admin';

    /**
     * @param  string|null  $nameOverride  solo para probar la guardia de nombre; el comando nunca lo pasa
     */
    public function __construct(private readonly ?string $nameOverride = null) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $evaluate
     * @return T
     *
     * @throws EvaluationDatabaseUnavailable
     */
    public function run(Closure $evaluate): mixed
    {
        $default = DB::getDefaultConnection();
        /** @var array<string, mixed> $operational */
        $operational = config("database.connections.{$default}");
        $operationalName = (string) ($operational['database'] ?? '');
        $name = $this->nameOverride ?? $operationalName.self::SUFFIX;

        if ($name === $operationalName || preg_match('/^[a-z0-9_]+$/', $name) !== 1) {
            throw new EvaluationDatabaseUnavailable((string) __('assistant.eval.database_name_clash'));
        }

        config(['database.connections.'.self::ADMIN_CONNECTION => $operational]);
        try {
            $this->recreate($name);
        } catch (QueryException|PDOException $e) {
            DB::purge(self::ADMIN_CONNECTION);
            throw new EvaluationDatabaseUnavailable((string) __('assistant.eval.database_unavailable'), previous: $e);
        }

        config(['database.connections.'.self::CONNECTION => [...$operational, 'database' => $name]]);
        try {
            Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]);
            DB::setDefaultConnection(self::CONNECTION);

            return $evaluate();
        } finally {
            DB::setDefaultConnection($default);
            DB::purge(self::CONNECTION);
            $this->drop($name);
        }
    }

    private function recreate(string $name): void
    {
        $admin = DB::connection(self::ADMIN_CONNECTION);
        $admin->statement("DROP DATABASE IF EXISTS \"{$name}\" WITH (FORCE)");
        $admin->statement("CREATE DATABASE \"{$name}\"");
    }

    private function drop(string $name): void
    {
        try {
            DB::connection(self::ADMIN_CONNECTION)->statement("DROP DATABASE IF EXISTS \"{$name}\" WITH (FORCE)");
        } catch (Throwable) {
            // No oculta el resultado ya calculado: queda el aviso y la próxima corrida la borra antes de crearla.
            Log::warning('assistant.eval.drop_failed');
        } finally {
            DB::purge(self::ADMIN_CONNECTION);
        }
    }
}
