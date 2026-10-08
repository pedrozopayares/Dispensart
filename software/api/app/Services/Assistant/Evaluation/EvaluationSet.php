<?php

namespace App\Services\Assistant\Evaluation;

use App\Enums\Role;
use App\Services\Assistant\Outcome;
use App\Services\Assistant\ToolCallStatus;
use App\Services\Assistant\Tools\ToolRegistry;
use Database\Seeders\AssistantEvalSeeder;

/**
 * Lee y valida el conjunto de evaluación versionado (assistant-evaluation «Conjunto de evaluación versionado»;
 * design D14) antes de evaluar nada: un defecto nombra la entrada y no se informa total.
 */
final class EvaluationSet
{
    public const MINIMUM_ENTRIES = 10;

    public const MARKER = '/\{transfer:([a-z_]+)\}/';

    public const TAGS = ['warehouse_filter', 'explicit_window', 'out_of_scope', 'write_request', 'patient_question', 'role_denied', 'malicious_note'];

    public function __construct(private readonly ToolRegistry $registry) {}

    public static function defaultPath(): string
    {
        return resource_path('assistant/evaluation-set.json');
    }

    /**
     * @return list<EvaluationEntry>
     *
     * @throws EvaluationSetInvalid
     */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new EvaluationSetInvalid((string) __('assistant.eval.file_missing', ['path' => $path]));
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! is_array($decoded['entries'] ?? null) || ! array_is_list($decoded['entries'])) {
            throw new EvaluationSetInvalid((string) __('assistant.eval.file_invalid'));
        }

        $entries = [];
        $ids = [];
        foreach ($decoded['entries'] as $position => $raw) {
            $entry = $this->entry(is_array($raw) ? $raw : [], $position + 1);
            if (in_array($entry->id, $ids, true)) {
                $this->fail("«{$entry->id}»", 'id repetido');
            }
            $ids[] = $entry->id;
            $entries[] = $entry;
        }

        if (count($entries) < self::MINIMUM_ENTRIES) {
            throw new EvaluationSetInvalid((string) __('assistant.eval.set_invalid', [
                'reason' => __('assistant.eval.too_few', ['count' => count($entries), 'minimum' => self::MINIMUM_ENTRIES]),
            ]));
        }

        return $entries;
    }

    /**
     * @param  array<mixed>  $raw
     */
    private function entry(array $raw, int $position): EvaluationEntry
    {
        $name = is_string($raw['id'] ?? null) && $raw['id'] !== '' ? "«{$raw['id']}»" : "#{$position}";
        $expect = is_array($raw['expect'] ?? null) ? $raw['expect'] : [];

        if (! is_string($raw['id'] ?? null) || $raw['id'] === '') {
            $this->fail($name, 'falta `id`');
        }
        $role = is_string($raw['role'] ?? null) ? Role::tryFrom($raw['role']) : null;
        if ($role === null) {
            $this->fail($name, '`role` ausente o desconocido');
        }
        $question = $raw['question'] ?? null;
        if (! is_string($question) || mb_strlen($question) < 3 || mb_strlen($question) > 500) {
            $this->fail($name, '`question` ausente o fuera de 3 a 500 caracteres');
        }
        if (! is_string($expect['outcome'] ?? null) || ! in_array($expect['outcome'], Outcome::values(), true)) {
            $this->fail($name, '`expect.outcome` ausente o desconocido');
        }
        $tags = $this->strings($raw['tags'] ?? [], $name, 'tags');
        if (array_diff($tags, self::TAGS) !== []) {
            $this->fail($name, 'etiqueta desconocida en `tags`');
        }
        $this->checkMarkers($question, $name);

        return new EvaluationEntry(
            (string) $raw['id'],
            $tags,
            $role,
            $question,
            $expect['outcome'],
            $this->tools($expect['tools'] ?? null, $name),
            $this->strings($expect['answer_contains'] ?? [], $name, 'answer_contains'),
            $this->strings($expect['answer_excludes'] ?? [], $name, 'answer_excludes'),
        );
    }

    /**
     * @return list<array{tool: string, status: string, arguments: array<string, mixed>}>
     */
    private function tools(mixed $raw, string $name): array
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            $this->fail($name, '`expect.tools` debe ser una lista');
        }

        $tools = [];
        foreach ($raw as $tool) {
            $toolName = is_array($tool) && is_string($tool['tool'] ?? null) ? $tool['tool'] : '';
            if ($this->registry->find($toolName) === null) {
                $this->fail($name, "herramienta «{$toolName}» fuera del catálogo");
            }
            $status = $tool['status'] ?? null;
            if (! is_string($status) || ! in_array($status, ToolCallStatus::values(), true)) {
                $this->fail($name, "estado de «{$toolName}» ausente o desconocido");
            }
            $arguments = $tool['arguments'] ?? [];
            if (! is_array($arguments) || ($arguments !== [] && array_is_list($arguments))) {
                $this->fail($name, "argumentos de «{$toolName}» deben ser un objeto");
            }
            foreach ($arguments as $value) {
                if (is_string($value)) {
                    $this->checkMarkers($value, $name);
                }
            }
            /** @var array<string, mixed> $arguments */
            $tools[] = ['tool' => $toolName, 'status' => $status, 'arguments' => $arguments];
        }

        return $tools;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $raw, string $name, string $field): array
    {
        if (! is_array($raw) || ! array_is_list($raw) || array_filter($raw, fn (mixed $v): bool => ! is_string($v)) !== []) {
            $this->fail($name, "`{$field}` debe ser una lista de textos");
        }

        /** @var list<string> */
        return $raw;
    }

    private function checkMarkers(string $text, string $name): void
    {
        preg_match_all(self::MARKER, $text, $matches);
        foreach ($matches[1] as $alias) {
            if (! in_array($alias, AssistantEvalSeeder::TRANSFER_ALIASES, true)) {
                $this->fail($name, "marcador de traslado «{$alias}» desconocido");
            }
        }
    }

    private function fail(string $entry, string $reason): never
    {
        throw new EvaluationSetInvalid((string) __('assistant.eval.set_invalid', ['reason' => "entrada {$entry}: {$reason}."]));
    }
}
