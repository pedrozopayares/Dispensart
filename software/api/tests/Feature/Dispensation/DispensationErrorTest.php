<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\AuthorizationRequired;
use App\Exceptions\AuthorizerMustDiffer;
use App\Exceptions\ExceedsPrescription;
use App\Exceptions\IdempotencyKeyReused;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidAuthorizer;
use App\Exceptions\InvalidIdempotencyKey;
use App\Exceptions\PrescriptionExhausted;
use App\Exceptions\PrescriptionExpired;
use App\Exceptions\TooManyAuthorizerAttempts;
use App\Services\Idempotency\IdempotencyStore;

// Render de los rechazos de S3 con la forma de S1 y mensajes en español (tarea 4.1).

it('mapea cada rechazo de dispensación a su estado, código y mensaje en español', function (Throwable $e, int $status, string $code) {
    $response = ApiExceptionRenderer::render($e);

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getData(true))->toBe(['code' => $code, 'message' => __('errors.'.$code)])
        ->and(__('errors.'.$code))->not->toBe('errors.'.$code);
})->with([
    'vencida' => [new PrescriptionExpired, 422, 'prescription_expired'],
    'agotada' => [new PrescriptionExhausted, 422, 'prescription_exhausted'],
    'más de lo pendiente' => [new ExceedsPrescription, 422, 'exceeds_prescription'],
    'falta autorización' => [new AuthorizationRequired, 422, 'authorization_required'],
    'autorizador = dispensador' => [new AuthorizerMustDiffer, 422, 'authorizer_must_differ'],
    'autorizador inválido' => [new InvalidAuthorizer, 422, 'invalid_authorizer'],
    'clave inválida' => [new InvalidIdempotencyKey, 422, 'invalid_idempotency_key'],
    'clave reutilizada' => [new IdempotencyKeyReused, 422, 'idempotency_key_reused'],
    'stock sin faltantes (libro)' => [new InsufficientStock, 409, 'insufficient_stock'],
]);

it('agrega shortages al 409 de stock insuficiente de la dispensación', function () {
    $shortages = [['prescription_item_id' => 7, 'product_id' => 3, 'requested' => 8, 'available' => 5]];

    expect(ApiExceptionRenderer::render(new InsufficientStock($shortages))->getData(true))->toBe([
        'code' => 'insufficient_stock', 'message' => __('errors.insufficient_stock'), 'shortages' => $shortages,
    ]);
});

it('responde 429 con Retry-After al agotar los intentos de autorizador', function () {
    $response = ApiExceptionRenderer::render(new TooManyAuthorizerAttempts(42));

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getData(true)['code'])->toBe('too_many_attempts')
        ->and($response->headers->get('Retry-After'))->toBe('42');
});

it('calcula la misma huella para el mismo cuerpo con claves en otro orden y otra para otra cantidad', function () {
    $a = IdempotencyStore::fingerprint('post', '/api/dispensations', ['warehouse_id' => 1, 'prescription_id' => 2, 'items' => [['quantity' => 3, 'prescription_item_id' => 4]]]);
    $b = IdempotencyStore::fingerprint('POST', '/api/dispensations', ['prescription_id' => 2, 'items' => [['prescription_item_id' => 4, 'quantity' => 3]], 'warehouse_id' => 1]);
    $c = IdempotencyStore::fingerprint('POST', '/api/dispensations', ['prescription_id' => 2, 'items' => [['prescription_item_id' => 4, 'quantity' => 4]], 'warehouse_id' => 1]);
    $d = IdempotencyStore::fingerprint('POST', '/api/transfers', ['prescription_id' => 2, 'items' => [['prescription_item_id' => 4, 'quantity' => 3]], 'warehouse_id' => 1]);

    expect($a)->toBe($b)->toMatch('/^[0-9a-f]{64}$/')
        ->and($c)->not->toBe($a)
        ->and($d)->not->toBe($a);
});
