<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\DiscrepancyAlreadyResolved;
use App\Exceptions\InvalidTransferTransition;
use App\Exceptions\SegregationOfDutiesViolation;

// tarea 4.1: rechazos nuevos de traslados con la forma D5 de S1, mensaje en español, sin traza ni SQL.

it('mapea cada rechazo de traslados a su estado y código estable', function (Throwable $e, int $status, string $code) {
    $response = ApiExceptionRenderer::render($e);

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getData(true))->toBe(['code' => $code, 'message' => __('errors.'.$code)])
        ->and(__('errors.'.$code))->not->toBe('errors.'.$code);
})->with([
    'transición inválida' => [new InvalidTransferTransition, 409, 'invalid_transfer_transition'],
    'segregación de funciones' => [new SegregationOfDutiesViolation, 403, 'segregation_of_duties'],
    'discrepancia ya resuelta' => [new DiscrepancyAlreadyResolved, 409, 'discrepancy_already_resolved'],
]);
