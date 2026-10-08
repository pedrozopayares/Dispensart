<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Forma única de rechazo para /api (design D5): {code, message[, errors]}.
 *
 * `code` es el contrato estable con la SPA; `message` sale de lang/es. Nunca traza, SQL, nombre de modelo
 * ni datos personales. Recibe la excepción ya preparada por el framework (AuthorizationException llega
 * como AccessDeniedHttpException, ModelNotFoundException como NotFoundHttpException, TokenMismatch como 419).
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof AuthenticationException => self::error('unauthenticated', 401),
            $e instanceof ValidationException => self::error('validation_failed', 422, ['errors' => $e->errors()]),
            $e instanceof InvalidCredentials => self::error('invalid_credentials', 422),
            $e instanceof TooManyLoginAttempts => self::error(
                'too_many_attempts', 429, headers: ['Retry-After' => (string) $e->retryAfterSeconds],
            ),
            $e instanceof InsufficientStock => self::error(
                'insufficient_stock', 409, $e->shortages === [] ? [] : ['shortages' => $e->shortages],
            ),
            $e instanceof LotExpired => self::error('lot_expired', 422),
            $e instanceof PrescriptionExpired => self::error('prescription_expired', 422),
            $e instanceof PrescriptionExhausted => self::error('prescription_exhausted', 422),
            $e instanceof ExceedsPrescription => self::error('exceeds_prescription', 422),
            $e instanceof AuthorizationRequired => self::error('authorization_required', 422),
            $e instanceof AuthorizerMustDiffer => self::error('authorizer_must_differ', 422),
            $e instanceof InvalidAuthorizer => self::error('invalid_authorizer', 422),
            $e instanceof TooManyAuthorizerAttempts => self::error(
                'too_many_attempts', 429, headers: ['Retry-After' => (string) $e->retryAfterSeconds],
            ),
            $e instanceof InvalidIdempotencyKey => self::error('invalid_idempotency_key', 422),
            $e instanceof IdempotencyKeyReused => self::error('idempotency_key_reused', 422),
            $e instanceof InvalidTransferTransition => self::error('invalid_transfer_transition', 409),
            $e instanceof SegregationOfDutiesViolation => self::error('segregation_of_duties', 403),
            $e instanceof DiscrepancyAlreadyResolved => self::error('discrepancy_already_resolved', 409),
            $e instanceof HttpExceptionInterface => self::error(
                self::codeForStatus($e->getStatusCode()), $e->getStatusCode(), headers: $e->getHeaders(),
            ),
            default => self::error('server_error', 500),
        };
    }

    private static function codeForStatus(int $status): string
    {
        return match (true) {
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 405 => 'method_not_allowed',
            $status === 419 => 'csrf_token_mismatch',
            $status === 429 => 'too_many_attempts',
            $status >= 500 => 'server_error',
            default => 'http_error',
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $headers
     */
    private static function error(string $code, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        $message = __('errors.'.$code);

        return new JsonResponse(['code' => $code, 'message' => $message, ...$extra], $status, $headers);
    }
}
