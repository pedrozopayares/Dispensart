<?php

namespace App\Console\Commands;

use App\Actions\Assistant\AskAssistant;
use App\Exceptions\AssistantUnavailable;
use App\Services\Assistant\Evaluation\EvaluationDatabase;
use App\Services\Assistant\Evaluation\EvaluationDatabaseUnavailable;
use App\Services\Assistant\Evaluation\EvaluationEntry;
use App\Services\Assistant\Evaluation\EvaluationMatcher;
use App\Services\Assistant\Evaluation\EvaluationSet;
use App\Services\Assistant\Evaluation\EvaluationSetInvalid;
use Database\Seeders\AssistantEvalSeeder;
use Illuminate\Console\Command;

/**
 * `php artisan assistant:eval` (assistant-evaluation «Comando que reporta aciertos»; design D14). Responde cada
 * pregunta del conjunto con AskAssistant (el mismo servicio de la ruta), el proveedor configurado y el usuario
 * sembrado del rol de la entrada, sobre una base desechable. Códigos: 0 solo si todas aciertan; 1 con algún
 * fallo; 2 si el conjunto o la base de evaluación no sirven (sin total).
 */
final class AssistantEvalCommand extends Command
{
    protected $signature = 'assistant:eval {--file= : Ruta del conjunto de evaluación (por defecto el versionado)}';

    protected $description = 'Evalúa el asistente de inventario contra el conjunto versionado y reporta aciertos.';

    public function handle(EvaluationSet $set, EvaluationMatcher $matcher, EvaluationDatabase $database): int
    {
        $option = $this->option('file');
        $path = is_string($option) && $option !== '' ? $option : EvaluationSet::defaultPath();

        try {
            $entries = $set->load($path);
            $rows = $database->run(fn (): array => $this->evaluate($entries, $matcher));
        } catch (EvaluationSetInvalid|EvaluationDatabaseUnavailable $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        $passed = count(array_filter($rows, fn (array $row): bool => $row[3] === __('assistant.eval.pass')));
        $this->table(['#', 'id', 'rol', 'resultado', 'expectativa incumplida'], $rows);
        $this->line((string) __('assistant.eval.total', ['passed' => $passed, 'total' => count($rows)]));

        return $passed === count($rows) && count($rows) >= EvaluationSet::MINIMUM_ENTRIES ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Corre ya dentro de la base de evaluación.
     *
     * @param  list<EvaluationEntry>  $entries
     * @return list<array{0: int, 1: string, 2: string, 3: string, 4: string}>
     */
    private function evaluate(array $entries, EvaluationMatcher $matcher): array
    {
        $seeder = new AssistantEvalSeeder;
        $seeder->setContainer($this->laravel);
        $world = $seeder->plant();
        $ask = $this->laravel->make(AskAssistant::class);

        $rows = [];
        foreach ($entries as $index => $entry) {
            $entry = $entry->resolve($world['transfers']);
            try {
                $failure = $matcher->firstFailure($entry, $ask->handle($world['users'][$entry->role->value], $entry->question));
            } catch (AssistantUnavailable) {
                $failure = (string) __('assistant.eval.unavailable');
            }
            $rows[] = [
                $index + 1,
                $entry->id,
                $entry->role->value,
                (string) __($failure === null ? 'assistant.eval.pass' : 'assistant.eval.fail'),
                $failure ?? '',
            ];
        }

        return $rows;
    }
}
