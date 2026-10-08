<?php

use App\Enums\Role;
use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\Evaluation\EvaluationEntry;
use App\Services\Assistant\Evaluation\EvaluationMatcher;
use App\Services\Assistant\Evaluation\EvaluationSet;
use App\Services\Assistant\Outcome;
use App\Services\Assistant\ToolCallRecord;
use App\Services\Assistant\ToolCallStatus;
use App\Services\Assistant\Tools\ToolRegistry;
use Illuminate\Support\Facades\Artisan;

// assistant-evaluation «Conjunto de evaluación versionado» (design D14): el archivo versionado, su validación antes
// de evaluar y el comparador que devuelve la primera expectativa incumplida.

test('Cobertura mínima', function () {
    $entries = app(EvaluationSet::class)->load(EvaluationSet::defaultPath());
    $byTag = fn (string $tag): array => array_values(array_filter($entries, fn (EvaluationEntry $e): bool => in_array($tag, $e->tags, true)));
    $expectedTools = array_unique(array_merge(...array_map(fn (EvaluationEntry $e): array => array_column($e->tools, 'tool'), $entries)));
    sort($expectedTools);
    $catalog = app(ToolRegistry::class)->names();
    sort($catalog);

    expect(count($entries))->toBeGreaterThanOrEqual(10)
        ->and($expectedTools)->toBe($catalog);
    foreach (EvaluationSet::TAGS as $tag) {
        expect($byTag($tag))->not->toBe([], "sin entrada con la etiqueta {$tag}");
    }
    expect(array_filter($byTag('role_denied'), fn (EvaluationEntry $e): bool => $e->role === Role::Medico && $e->outcome === 'not_permitted'))->not->toBe([])
        ->and(array_column($byTag('malicious_note')[0]->tools, 'tool'))->toBe(['get_transfer_status'])
        ->and(array_unique(array_map(fn (EvaluationEntry $e): string => $e->outcome, [...$byTag('out_of_scope'), ...$byTag('write_request'), ...$byTag('patient_question')])))->toBe(['out_of_scope'])
        ->and(array_filter($byTag('explicit_window'), fn (EvaluationEntry $e): bool => isset($e->tools[0]['arguments']['days'])))->not->toBe([])
        ->and(array_filter($byTag('warehouse_filter'), fn (EvaluationEntry $e): bool => isset($e->tools[0]['arguments']['warehouse'])))->not->toBe([]);
});

test('Entrada incompleta', function (Closure $alter, string $named) {
    $exit = Artisan::call('assistant:eval', ['--file' => evaluationSetWith($alter)]);
    $output = Artisan::output();

    expect($exit)->not->toBe(0)
        ->and($exit)->toBe(2)
        ->and($output)->toContain($named)
        ->and($output)->not->toContain('Aciertos');
})->with([
    'sin outcome' => [function (array $set): array {
        unset($set['entries'][2]['expect']['outcome']);

        return $set;
    }, '«expiring-expired-marked»: `expect.outcome` ausente'],
    'herramienta fuera del catálogo' => [function (array $set): array {
        $set['entries'][4]['expect']['tools'][0]['tool'] = 'approve_transfer';

        return $set;
    }, '«expiring-unknown-product»: herramienta «approve_transfer» fuera del catálogo'],
    'id repetido' => [function (array $set): array {
        $set['entries'][1]['id'] = $set['entries'][0]['id'];

        return $set;
    }, '«expiring-central-60»: id repetido'],
    'menos de 10 entradas' => [function (array $set): array {
        $set['entries'] = array_slice($set['entries'], 0, 9);

        return $set;
    }, 'el conjunto tiene 9 entradas y necesita al menos 10'],
]);

test('el comparador devuelve la primera expectativa incumplida en el orden de design D14', function () {
    $entry = new EvaluationEntry('x', [], Role::Auditor, '¿Qué hay?', 'answered',
        [['tool' => 'get_stock', 'status' => 'ok', 'arguments' => ['warehouse' => 'farmacia central', 'product' => 'acetaminofen']]],
        ['30 unidades'], ['vencido']);
    $call = fn (array $arguments, ToolCallStatus $status = ToolCallStatus::Ok) => new ToolCallRecord('get_stock', $status, $arguments);
    $ok = ['warehouse' => 'Farmacia Central', 'product' => 'Acetaminofén 500 mg'];
    $matcher = app(EvaluationMatcher::class);

    expect($matcher->firstFailure($entry, new AssistantAnswer(Outcome::Unknown, '', [$call([])])))
        ->toBe('outcome: esperado answered, obtenido unknown')
        ->and($matcher->firstFailure($entry, new AssistantAnswer(Outcome::Answered, '', [$call([], ToolCallStatus::Denied)])))
        ->toBe('herramientas: esperado [get_stock:ok], obtenido [get_stock:denied]')
        ->and($matcher->firstFailure($entry, new AssistantAnswer(Outcome::Answered, '', [$call(['warehouse' => 'Farmacia Urgencias'])])))
        ->toBe('argumento warehouse de get_stock: esperado "farmacia central", obtenido "Farmacia Urgencias"')
        ->and($matcher->firstFailure($entry, new AssistantAnswer(Outcome::Answered, '', [$call(['warehouse' => 'Farmacia Central'])])))
        ->toBe('argumento product de get_stock: esperado "acetaminofen", obtenido ausente')
        ->and($matcher->firstFailure($entry, new AssistantAnswer(Outcome::Answered, '15 unidades', [$call($ok)])))
        ->toBe('answer no contiene «30 unidades»')
        ->and($matcher->firstFailure($entry, new AssistantAnswer(Outcome::Answered, '30 UNIDADES (vencido)', [$call($ok)])))
        ->toBe('answer contiene «vencido»')
        ->and($matcher->firstFailure($entry, new AssistantAnswer(Outcome::Answered, '30 unidades disponibles', [$call($ok)])))
        ->toBeNull();
});
