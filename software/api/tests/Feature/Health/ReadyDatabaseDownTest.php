<?php

// Sin RefreshDatabase a propósito: la base queda inalcanzable (puerto cerrado, driver real).

beforeEach(function () {
    $this->dbHost = (string) config('database.connections.pgsql.host');
    $this->dbUser = (string) config('database.connections.pgsql.username');
    $this->dbName = (string) config('database.connections.pgsql.database');
    $this->logPath = captureLog();
    makeDatabaseUnreachable();
});

it('responde 503 con database fail y migrations skipped si la base es inalcanzable', function () {
    $this->get('/ready')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'not_ready', 'checks' => ['database' => 'fail', 'migrations' => 'skipped']]);
});

it('no filtra detalles internos en el cuerpo y registra la excepción con el correlation_id', function () {
    $response = $this->withHeader('X-Correlation-Id', 'traza-db-caida')->get('/ready');

    $response->assertStatus(503);
    $body = (string) $response->getContent();
    foreach (['SQLSTATE', 'Connection', 'refused', $this->dbHost, $this->dbUser, $this->dbName, 'port'] as $leak) {
        expect($body)->not->toContain($leak);
    }

    $errors = array_values(array_filter(
        logLines($this->logPath),
        fn (array $line): bool => $line['level'] === 'error',
    ));

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['correlation_id'])->toBe('traza-db-caida')
        ->and($errors[0]['message'])->toContain('SQLSTATE'); // la causa queda en el log, no en la respuesta
});
