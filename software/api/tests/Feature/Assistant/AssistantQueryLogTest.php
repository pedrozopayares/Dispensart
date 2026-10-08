<?php

use App\Enums\Role;
use App\Exceptions\AssistantUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// inventory-assistant «Registro de consultas sin contenido» (design D13), con el canal real redirigido a archivo.

beforeEach(function () {
    freezeAlertClock();
    $this->world = assistantWorld();
    $this->logPath = captureLog();
});

/**
 * @return list<array<string, mixed>>
 */
function assistantLines(string $path): array
{
    return array_values(array_filter(logLines($path), fn (array $line): bool => $line['message'] === 'assistant.query'));
}

test('Línea de la consulta: outcome, llamadas, rondas, proveedor, duración y correlation_id', function () {
    $this->actingAs(worldUser($this->world, Role::AuxiliarFarmacia))
        ->withHeader('X-Correlation-Id', 'traza-asistente')
        ->postJson('/api/assistant/ask', ['question' => '¿Cuánto stock hay de acetaminofén en la farmacia central?'])
        ->assertOk();

    $lines = assistantLines($this->logPath);
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['correlation_id'])->toBe('traza-asistente')
        ->and(array_keys($lines[0]['context']))->toBe(['outcome', 'tool_calls', 'rounds', 'provider', 'duration_ms'])
        ->and($lines[0]['context']['outcome'])->toBe('answered')
        ->and($lines[0]['context']['tool_calls'])->toBe([['tool' => 'get_stock', 'status' => 'ok']])
        ->and($lines[0]['context']['rounds'])->toBe(2)
        ->and($lines[0]['context']['provider'])->toBe('mock')
        ->and($lines[0]['context']['duration_ms'])->toBeNumeric();
    // Ni pregunta, ni argumentos de texto, ni respuesta, ni resultados.
    $raw = (string) file_get_contents($this->logPath);
    foreach (['acetaminof', 'Acetaminof', 'farmacia central', 'Farmacia Central', 'EVAL-ACE', '30 unidades', 'question'] as $forbidden) {
        expect($raw)->not->toContain($forbidden);
    }
});

test('Pregunta sensible fuera del log; el mismo barrido sí detecta el documento escrito a propósito', function () {
    $question = '¿Qué le dispensaron a Ana Sintética Pérez, documento 9999010001?';
    $forbidden = ['Ana Sintética Pérez', 'Ana Sintética Pérez', 'Sint', '9999010001'];
    $sweep = function (string $raw) use ($forbidden): array {
        return array_values(array_filter($forbidden, fn (string $needle): bool => str_contains($raw, $needle)));
    };

    ask(worldUser($this->world, Role::RegenteFarmacia), $question)->assertOk()->assertJsonPath('data.outcome', 'out_of_scope');

    $lines = assistantLines($this->logPath);
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['context']['outcome'])->toBe('out_of_scope')
        ->and($lines[0]['context']['rounds'])->toBe(0)
        ->and($sweep((string) file_get_contents($this->logPath)))->toBe([]);

    // Control positivo: el mismo barrido encuentra el documento cuando se escribe a propósito.
    Log::info('control positivo', ['documento' => '9999010001']);
    expect($sweep((string) file_get_contents($this->logPath)))->toBe(['9999010001']);
});

test('ante un 503 también queda exactamente una línea, con outcome assistant_unavailable', function () {
    scriptProvider(always: fn () => throw new AssistantUnavailable('caído'));

    ask(worldUser($this->world, Role::AuxiliarFarmacia), '¿Qué existencias hay?')->assertStatus(503);

    $lines = assistantLines($this->logPath);
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['context']['outcome'])->toBe('assistant_unavailable')
        ->and($lines[0]['context']['rounds'])->toBe(1)
        ->and($lines[0]['context']['provider'])->toBe('scripted')
        // dontReport: sin línea de error con la excepción (niveles PSR en minúscula, ver JsonLineFormatter).
        ->and(array_filter(logLines($this->logPath), fn (array $line): bool => in_array($line['level'], ['error', 'critical', 'alert', 'emergency'], true)))->toBe([])
        ->and(array_column(logLines($this->logPath), 'level'))->toContain('info');
});

test('nombre de herramienta inventado por el modelo: saneado en el log y en tool_calls de la respuesta', function () {
    scriptProvider([callTool("run_sql\"}\n{\"message\": \"falso\"}\r\nX-Linea: 1")]);

    ask(worldUser($this->world, Role::AuxiliarFarmacia), '¿Qué existencias hay?')
        ->assertOk()
        ->assertJsonPath('data.tool_calls.0.tool', 'run_sqlmessagefalsoX-Linea1')
        ->assertJsonPath('data.tool_calls.0.status', 'rejected');

    $lines = assistantLines($this->logPath);
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['context']['tool_calls'])->toBe([['tool' => 'run_sqlmessagefalsoX-Linea1', 'status' => 'rejected']])
        ->and((string) file_get_contents($this->logPath))->not->toContain('falso\"');
});
