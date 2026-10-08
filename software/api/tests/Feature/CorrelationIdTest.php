<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->logPath = captureLog();
});

it('respeta un X-Correlation-Id válido', function () {
    $this->withHeader('X-Correlation-Id', 'pedido-123.abc')->get('/health')
        ->assertOk()
        ->assertHeader('X-Correlation-Id', 'pedido-123.abc');
});

it('acepta el límite de 128 caracteres válidos', function () {
    $atLimit = str_repeat('a', 128);

    $this->withHeader('X-Correlation-Id', $atLimit)->get('/health')
        ->assertHeader('X-Correlation-Id', $atLimit);
});

it('genera UUIDs distintos cuando la cabecera falta', function () {
    $first = $this->get('/health')->headers->get('X-Correlation-Id');
    $second = $this->get('/health')->headers->get('X-Correlation-Id');

    expect(Str::isUuid($first))->toBeTrue()
        ->and(Str::isUuid($second))->toBeTrue()
        ->and($first)->not->toBe($second);
});

it('reemplaza una cabecera inválida por un UUID y no la registra en el log', function (string $invalid) {
    $response = $this->withHeader('X-Correlation-Id', $invalid)->get('/health');

    $effective = $response->headers->get('X-Correlation-Id');
    expect(Str::isUuid($effective))->toBeTrue()
        ->and($effective)->not->toBe($invalid);

    $raw = (string) file_get_contents($this->logPath);
    $lines = logLines($this->logPath);
    expect($lines)->not->toBeEmpty() // control positivo: hubo log de la petición
        ->and(array_column($lines, 'correlation_id'))->each->toBe($effective)
        ->and($raw)->not->toContain($invalid)
        ->and($raw)->not->toContain(trim((string) json_encode($invalid), '"'));
})->with([
    '129 caracteres' => str_repeat('a', 129),
    'espacios' => 'pedido con espacios',
    'salto de línea' => "pedido\ninyectado",
    'caracteres fuera del conjunto' => 'pedido-ñandú;drop',
]);

it('responde 500 con X-Correlation-Id, sin traza en el cuerpo, y lo registra en la línea del error', function () {
    config(['app.debug' => true]); // peor caso: ni en depuración se expone la traza
    Route::get('/api/test-unhandled', fn () => throw new RuntimeException('detalle interno sintético'));

    $response = $this->withHeader('X-Correlation-Id', 'traza-500')->getJson('/api/test-unhandled');

    $response->assertStatus(500)
        ->assertHeader('X-Correlation-Id', 'traza-500')
        ->assertExactJson(['code' => 'server_error', 'message' => 'Ocurrió un error interno. Informe a soporte el identificador de correlación.']);
    expect((string) $response->getContent())->not->toContain('detalle interno sintético')
        ->not->toContain('trace')
        ->not->toContain('.php');

    $errors = array_values(array_filter(logLines($this->logPath), fn (array $line): bool => $line['level'] === 'error'));
    expect($errors)->toHaveCount(1)
        ->and($errors[0]['correlation_id'])->toBe('traza-500')
        ->and($errors[0]['message'])->toBe('detalle interno sintético');
});
