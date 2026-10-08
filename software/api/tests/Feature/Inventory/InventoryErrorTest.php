<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\InsufficientStock;
use App\Exceptions\LotExpired;
use Illuminate\Database\QueryException;

// design D10: rechazos nuevos con la forma D5 de S1, mensaje en español, sin cantidades, traza ni SQL.

it('mapea cada rechazo de inventario a su estado y código estable', function (Throwable $e, int $status, string $code) {
    $response = ApiExceptionRenderer::render($e);

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getData(true))->toBe(['code' => $code, 'message' => __('errors.'.$code)])
        ->and(__('errors.'.$code))->not->toBe('errors.'.$code);
})->with([
    'existencia insuficiente' => [new InsufficientStock, 409, 'insufficient_stock'],
    'lote vencido' => [new LotExpired, 422, 'lot_expired'],
]);

it('trata una violación de restricción de la base como defecto: 500 sin SQL', function () {
    $e = new QueryException('pgsql', 'UPDATE stocks SET quantity = -1', [], new PDOException('stocks_quantity_non_negative'));

    $response = ApiExceptionRenderer::render($e);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toBe(['code' => 'server_error', 'message' => __('errors.server_error')]);
});
