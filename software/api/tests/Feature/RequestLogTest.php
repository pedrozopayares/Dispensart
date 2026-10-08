<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->logPath = captureLog();
});

/**
 * @param  list<array<string, mixed>>  $lines
 * @return list<array<string, mixed>>
 */
function closingLines(array $lines): array
{
    return array_values(array_filter($lines, fn (array $line): bool => $line['message'] === 'request.completed'));
}

it('escribe exactamente una línea de cierre por petición con método, ruta, estado y duración', function () {
    $response = $this->withHeader('X-Correlation-Id', 'traza-1')->get('/ready');

    $closing = closingLines(logLines($this->logPath));

    expect($closing)->toHaveCount(1);
    expect($closing[0]['correlation_id'])->toBe('traza-1')
        ->and($closing[0]['context']['method'])->toBe('GET')
        ->and($closing[0]['context']['path'])->toBe('/ready')
        ->and($closing[0]['context']['status'])->toBe($response->getStatusCode())
        ->and($closing[0]['context']['duration_ms'])->toBeNumeric();
});

it('escribe cada línea como objeto JSON con timestamp, level, message y correlation_id', function () {
    Route::get('/api/test-unhandled', fn () => throw new RuntimeException('fallo sintético'));

    $this->get('/health');
    $this->get('/ready');
    $this->getJson('/api/no-existe');
    $this->getJson('/api/test-unhandled');

    $raw = array_values(array_filter(explode("\n", (string) file_get_contents($this->logPath))));
    expect($raw)->toHaveCount(5); // control positivo: 4 cierres + 1 error

    foreach ($raw as $line) {
        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        expect($decoded)->toBeArray()->toHaveKeys(['timestamp', 'level', 'message', 'correlation_id'])
            ->and($decoded['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+00:00$/');
    }
});

it('excluye query string, cuerpo, cookies y cabecera Authorization del log', function () {
    $this->withUnencryptedCookie('sesion_sintetica', 'galleta-secreta-789')
        ->withHeaders(['Authorization' => 'Bearer token-sintetico-xyz', 'X-Correlation-Id' => 'traza-privacidad'])
        ->postJson('/health?documento=123456', ['nombre' => 'Paciente Sintético']);

    $raw = (string) file_get_contents($this->logPath);
    $closing = closingLines(logLines($this->logPath));

    // Control positivo: la petición sí quedó registrada.
    expect($closing)->toHaveCount(1)
        ->and($closing[0]['context']['method'])->toBe('POST')
        ->and($closing[0]['context']['path'])->toBe('/health');

    foreach (['documento', '123456', 'Paciente', 'Sint', 'galleta-secreta-789', 'sesion_sintetica', 'token-sintetico-xyz', 'Bearer'] as $forbidden) {
        expect($raw)->not->toContain($forbidden);
    }
});

it('escribe JSON válido con correlation_id null fuera de una petición', function () {
    Log::info('migración de arranque');

    $lines = logLines($this->logPath);

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toHaveKey('correlation_id')
        ->and($lines[0]['correlation_id'])->toBeNull()
        ->and($lines[0]['message'])->toBe('migración de arranque');
});
