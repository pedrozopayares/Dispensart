<?php

use Illuminate\Support\Facades\DB;

// Sin RefreshDatabase: una prueba deja la base inalcanzable a propósito.

it('responde 200 con el cuerpo exacto {"status":"ok"} con la base disponible', function () {
    DB::connection()->select('select 1'); // control positivo: la base responde

    $response = $this->get('/health');

    $response->assertOk();
    expect($response->getContent())->toBe('{"status":"ok"}');
});

it('responde 200 con {"status":"ok"} aunque la base de datos esté caída', function () {
    makeDatabaseUnreachable();

    // Control positivo: la base de verdad no responde.
    expect(fn () => DB::connection()->select('select 1'))->toThrow(PDOException::class);

    $response = $this->get('/health');

    $response->assertOk();
    expect($response->getContent())->toBe('{"status":"ok"}');
});

it('no emite cookies ni más clave que status', function () {
    $response = $this->get('/health');

    $response->assertOk()->assertHeaderMissing('Set-Cookie');
    expect($response->headers->getCookies())->toBe([])
        ->and(array_keys($response->json()))->toBe(['status']);
});
