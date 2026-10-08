<?php

namespace App\Http\Requests\Auth;

use App\Actions\Identity\LoginAction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Login desde la SPA. Sin sesión (origen ajeno a SANCTUM_STATEFUL_DOMAINS, o sin Origin/Referer) se
 * rechaza con 403 antes de validar: sin consulta a la base, sin limitador y sin cookie (design D1).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasSession();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => LoginAction::normalizeEmail($this->input('email'))]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
