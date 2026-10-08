<?php

it('responde 404 en JSON con X-Correlation-Id a una ruta desconocida bajo /api', function (array $headers) {
    $response = $this->withHeaders($headers)->get('/api/no-existe');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['code' => 'not_found', 'message' => 'El recurso solicitado no existe.']);
    expect($response->headers->get('X-Correlation-Id'))->not->toBeEmpty()
        ->and((string) $response->getContent())->not->toContain('<html');
})->with([
    'con Accept JSON' => [['Accept' => 'application/json']],
    'sin Accept' => [[]],
]);
