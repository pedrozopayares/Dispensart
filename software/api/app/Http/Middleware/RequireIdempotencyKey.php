<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidIdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige `Idempotency-Key` de 16 a 128 caracteres [A-Za-z0-9_-] (RN-09, design D4 paso 3). Va en la lista de
 * prioridad justo después de Authorize: un rol sin permiso recibe 403 antes que el 422 de la clave.
 */
final class RequireIdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    private const PATTERN = '/\A[A-Za-z0-9_-]{16,128}\z/';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);

        if ($key === null || preg_match(self::PATTERN, $key) !== 1) {
            throw new InvalidIdempotencyKey;
        }

        return $next($request);
    }
}
