<?php

namespace App\Services\Dispensation;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Exceptions\AuthorizationRequired;
use App\Exceptions\AuthorizerMustDiffer;
use App\Exceptions\InvalidAuthorizer;
use App\Exceptions\TooManyAuthorizerAttempts;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Services\Identity\CredentialVerifier;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Coautorización de control especial en la misma petición (RN-05, design D6). Se ejecuta fuera de la
 * transacción de la dispensación (bcrypt nunca corre con filas bloqueadas) y en este orden:
 * faltan datos → autorizador = dispensador → limitador → credenciales + capacidad.
 * Correo inexistente, contraseña errada y usuario sin controlled_drugs.authorize dan la misma excepción.
 * La contraseña nunca se guarda ni se registra: ni en el limitador (correo con hash), ni en la bitácora.
 */
final class ControlledDrugAuthorizer
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function __construct(
        private readonly CredentialVerifier $credentials,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @throws AuthorizationRequired|AuthorizerMustDiffer|TooManyAuthorizerAttempts|InvalidAuthorizer
     */
    public function verify(User $dispenser, ?string $email, ?string $password, int $prescriptionId, int $warehouseId): User
    {
        if ($email === null || $email === '' || $password === null || $password === '') {
            throw new AuthorizationRequired;
        }

        $email = CredentialVerifier::normalizeEmail($email);
        if ($email === CredentialVerifier::normalizeEmail($dispenser->email)) {
            throw new AuthorizerMustDiffer;
        }

        // Sin correo en claro en el almacén del limitador.
        $key = 'controlled-authorizer|'.$dispenser->id.'|'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyAuthorizerAttempts(RateLimiter::availableIn($key));
        }

        $authorizer = $this->credentials->verify($email, $password);
        if ($authorizer === null || ! $authorizer->role->allows(Ability::ControlledDrugsAuthorize->value)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->audit->record(
                $dispenser->id,
                AuditAction::ControlledDrugAuthorizationFailed,
                $prescriptionId,
                ['warehouse_id' => $warehouseId],
            );

            throw new InvalidAuthorizer;
        }

        RateLimiter::clear($key);

        return $authorizer;
    }
}
