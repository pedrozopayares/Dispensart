<?php

use App\Enums\Role;
use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\Evaluation\EvaluationEntry;
use App\Services\Assistant\Evaluation\EvaluationMatcher;
use App\Services\Assistant\Evaluation\EvaluationSet;
use App\Services\Assistant\Llm\CatalogVocabulary;
use App\Services\Assistant\Llm\ChatRequest;
use App\Services\Assistant\Llm\ChatResponse;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\MockLlmProvider;
use App\Services\Assistant\Llm\ToolCall;
use App\Services\Assistant\Outcome;
use App\Services\Assistant\Text;
use App\Services\Assistant\ToolCallRecord;
use App\Services\Assistant\ToolCallStatus;
use Database\Seeders\AssistantEvalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

// assistant-evaluation «Argumentos de catálogo comparados por entidad resuelta» (salda D-auv-7): `warehouse` y
// `product` se comparan por la bodega o el producto que resuelven en el catálogo de evaluación, con el mismo
// CatalogResolver de las herramientas. El catálogo lo siembra el mismo AssistantEvalSeeder de `assistant:eval`.

beforeEach(function () {
    (new AssistantEvalSeeder)->setContainer(app())->plant();
});

/**
 * Primera expectativa incumplida de una entrada con una sola herramienta esperada, frente a una llamada `ok` a esa
 * herramienta con los argumentos obtenidos.
 *
 * @param  array<string, mixed>  $expected
 * @param  array<string, mixed>  $actual
 */
function catalogFailure(string $tool, array $expected, array $actual): ?string
{
    $entry = new EvaluationEntry('x', [], Role::RegenteFarmacia, '¿Qué hay?', 'answered',
        [['tool' => $tool, 'status' => 'ok', 'arguments' => $expected]], [], []);

    return app(EvaluationMatcher::class)->firstFailure(
        $entry,
        new AssistantAnswer(Outcome::Answered, '', [new ToolCallRecord($tool, ToolCallStatus::Ok, $actual)]),
    );
}

/**
 * `assistant:eval` real con el conjunto versionado y un proveedor guionado: el modo simulado, salvo que la bodega
 * «Farmacia Urgencias» que elige se envía con el texto dado (a toda herramienta, o solo a `$tool`).
 *
 * @return array{0: int, 1: string}
 */
function evalWithUrgenciasAs(string $warehouse, ?string $tool = null): array
{
    $mock = new MockLlmProvider(app(CatalogVocabulary::class));
    app()->instance(LlmProvider::class, new class($mock, $warehouse, $tool) implements LlmProvider
    {
        public function __construct(private readonly LlmProvider $inner, private readonly string $warehouse, private readonly ?string $tool) {}

        public function name(): string
        {
            return 'guionado';
        }

        public function chat(ChatRequest $request): ChatResponse
        {
            $response = $this->inner->chat($request);
            if (! $response->hasToolCalls()) {
                return $response;
            }

            return ChatResponse::toolCalls(array_map(function (ToolCall $call): ToolCall {
                $arguments = is_array($call->arguments) ? $call->arguments : [];
                $targeted = $this->tool === null || $this->tool === $call->name;
                if ($targeted && is_string($arguments['warehouse'] ?? null) && Text::normalize($arguments['warehouse']) === 'farmacia urgencias') {
                    $arguments['warehouse'] = $this->warehouse;
                }

                return new ToolCall($call->id, $call->name, $arguments);
            }, $response->toolCalls));
        }
    });

    $buffer = new BufferedOutput;
    $exit = Artisan::call('assistant:eval', [], $buffer);

    return [$exit, $buffer->fetch()];
}

/**
 * Filas `| # | id | rol | resultado | expectativa |` de la tabla del comando, por id.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function evalRows(string $output): array
{
    preg_match_all('/^\|\s+\d+\s+\|\s+(\S+)\s+\|\s+\S+\s+\|\s+(ACIERTO|FALLO)\s+\|\s+(.*?)\s+\|$/mu', $output, $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn (array $row): array => [$row[1] => [$row[2], $row[3]]])->all();
}

test('Misma bodega con otra redacción', function () {
    expect(catalogFailure('get_stock', ['warehouse' => 'farmacia urgencias'], ['warehouse' => 'farmacia de urgencias']))->toBeNull();

    [$exit, $output] = evalWithUrgenciasAs('farmacia de urgencias');
    $rows = evalRows($output);
    $total = count(app(EvaluationSet::class)->load(EvaluationSet::defaultPath()));

    expect($rows)->toHaveCount($total)
        ->and($rows['stock-without-expired'])->toBe(['ACIERTO', ''])
        ->and($rows['low-stock-equal-minimum'])->toBe(['ACIERTO', ''])
        ->and($output)->toContain("Aciertos: {$total}/{$total}")
        ->and($exit)->toBe(0);
});

test('Mismo producto con otra redacción', function () {
    expect(catalogFailure('get_stock', ['product' => 'acetaminofen'], ['product' => 'ACETAMINOFÉN 500 MG']))->toBeNull()
        ->and(catalogFailure('get_stock', ['product' => 'acetaminofen'], ['product' => 'Acetaminofén']))->toBeNull()
        // Ninguno contiene al otro: solo la resolución del catálogo los iguala (la contención de texto fallaría).
        ->and(catalogFailure('get_stock', ['product' => 'acetaminofen 500 mg'], ['product' => 'Acetaminofén']))->toBeNull()
        ->and(catalogFailure('get_stock', ['product' => 'acetaminofen 500 mg'], ['product' => 'Ibuprofeno']))
        ->toBe('argumento product de get_stock: esperado "acetaminofen 500 mg", obtenido "Ibuprofeno"');
});

test('Otra bodega sigue fallando', function () {
    expect(catalogFailure('get_stock', ['warehouse' => 'farmacia urgencias'], ['warehouse' => 'farmacia central']))
        ->toBe('argumento warehouse de get_stock: esperado "farmacia urgencias", obtenido "farmacia central"');

    // Comando real: Farmacia Central sí tiene alertas, y el orden de D14 reporta primero el outcome; el texto del
    // argumento lo fija la aserción del comparador de arriba.
    [$exit, $output] = evalWithUrgenciasAs('farmacia central', 'get_low_stock_alerts');
    $rows = evalRows($output);
    $total = count(app(EvaluationSet::class)->load(EvaluationSet::defaultPath()));

    expect($rows)->toHaveCount($total)
        ->and(array_keys(array_filter($rows, fn (array $row): bool => $row[0] === 'FALLO')))->toBe(['low-stock-equal-minimum'])
        ->and($output)->toContain('Aciertos: '.($total - 1)."/{$total}")
        ->and($exit)->not->toBe(0);
});

test('Otro producto sigue fallando', function () {
    expect(catalogFailure('get_stock', ['product' => 'acetaminofen'], ['product' => 'ibuprofeno']))
        ->toBe('argumento product de get_stock: esperado "acetaminofen", obtenido "ibuprofeno"');
});

test('Texto obtenido ambiguo o inexistente', function (array $actual, string $got) {
    expect(catalogFailure('get_stock', ['warehouse' => 'farmacia urgencias'], $actual))
        ->toBe("argumento warehouse de get_stock: esperado \"farmacia urgencias\", obtenido {$got}");
})->with([
    'ambiguo' => [['warehouse' => 'farmacia'], '"farmacia"'],
    'inexistente' => [['warehouse' => 'bodega inexistente'], '"bodega inexistente"'],
    'ausente' => [[], 'ausente'],
]);

test('Texto esperado sin resolución', function () {
    expect(catalogFailure('find_expiring_lots', ['product' => 'zzzmedicamento'], ['product' => 'zzzmedicamento']))->toBeNull()
        ->and(catalogFailure('find_expiring_lots', ['product' => 'zzzmedicamento'], ['product' => 'yyyotro']))
        ->toBe('argumento product de find_expiring_lots: esperado "zzzmedicamento", obtenido "yyyotro"');
});

test('Demás argumentos sin cambio', function () {
    expect(catalogFailure('find_expiring_lots', ['days' => 60], ['days' => 30]))
        ->toBe('argumento days de find_expiring_lots: esperado 60, obtenido 30')
        ->and(catalogFailure('get_transfer_status', ['status' => 'EN_TRANSITO'], ['status' => 'SOLICITADO']))
        ->toBe('argumento status de get_transfer_status: esperado "EN_TRANSITO", obtenido "SOLICITADO"')
        // Control positivo: el mismo comparador acepta los valores esperados.
        ->and(catalogFailure('find_expiring_lots', ['days' => 60], ['days' => 60]))->toBeNull()
        ->and(catalogFailure('get_transfer_status', ['status' => 'EN_TRANSITO'], ['status' => 'EN_TRANSITO']))->toBeNull();
});
