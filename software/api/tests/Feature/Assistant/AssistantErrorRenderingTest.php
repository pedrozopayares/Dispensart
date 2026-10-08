<?php

use App\Enums\Role;
use App\Exceptions\AssistantUnavailable;
use App\Services\Assistant\Llm\ChatRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Rechazos nuevos del asistente con la forma D5 de S1 (design D12): 503 assistant_unavailable y 429
// too_many_requests. inventory-assistant «Exceso de preguntas» por la ruta real.

beforeEach(function () {
    freezeAlertClock();
    $this->world = assistantWorld();
});

test('503 assistant_unavailable: forma estable sin traza ni texto del proveedor', function () {
    scriptProvider(always: fn (ChatRequest $request) => throw new AssistantUnavailable('detalle interno http://interno:11434'));
    config(['app.debug' => true]);

    $response = ask(worldUser($this->world, Role::RegenteFarmacia), '¿Qué existencias hay?');

    $response->assertStatus(503)->assertExactJson([
        'code' => 'assistant_unavailable',
        'message' => 'El asistente no está disponible en este momento. Intenta más tarde.',
    ]);
    expect($response->getContent())->not->toContain('interno')->not->toContain('trace');
});

test('Exceso de preguntas: la 21 recibe 429 too_many_requests con Retry-After y no llega al proveedor', function () {
    $recorder = recordProvider();
    $auditor = worldUser($this->world, Role::Auditor);

    foreach (range(1, 20) as $n) {
        ask($auditor, '¿Qué existencias hay en la bodega de hospitalización?')->assertOk();
    }
    $callsBefore = count($recorder->requests);
    $response = ask($auditor, '¿Qué existencias hay en la bodega de hospitalización?');

    $response->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertJsonPath('message', 'Hiciste demasiadas preguntas al asistente. Espera un momento antes de volver a intentar.')
        ->assertHeader('Retry-After');
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and(count($recorder->requests))->toBe($callsBefore)
        ->and($callsBefore)->toBe(40);
    // El límite es por usuario: otro usuario sigue respondido.
    ask(worldUser($this->world, Role::RegenteFarmacia), '¿Qué existencias hay en la bodega de hospitalización?')->assertOk();
});
