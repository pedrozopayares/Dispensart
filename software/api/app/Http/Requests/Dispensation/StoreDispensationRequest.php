<?php

namespace App\Http\Requests\Dispensation;

use App\Http\Middleware\RequireIdempotencyKey;
use App\Services\Idempotency\IdempotencyStore;
use App\Support\RoutePattern;

/**
 * Dispensación: las reglas de la vista previa más las credenciales opcionales del autorizador (RN-05). El
 * dispensador nunca se lee del cuerpo. La huella de idempotencia usa solo `dispensation()`: credenciales y
 * campos ajenos no la alteran (design D5).
 */
final class StoreDispensationRequest extends PreviewDispensationRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'authorizer_email' => ['nullable', 'string', 'max:255'],
            'authorizer_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function authorizerEmail(): ?string
    {
        $email = $this->validated('authorizer_email');

        return is_string($email) ? $email : null;
    }

    public function authorizerPassword(): ?string
    {
        $password = $this->validated('authorizer_password');

        return is_string($password) ? $password : null;
    }

    /**
     * Clave ya validada por RequireIdempotencyKey.
     */
    public function idempotencyKey(): string
    {
        return (string) $this->header(RequireIdempotencyKey::HEADER);
    }

    public function fingerprint(): string
    {
        return IdempotencyStore::fingerprint($this->method(), RoutePattern::of($this), $this->dispensation());
    }
}
