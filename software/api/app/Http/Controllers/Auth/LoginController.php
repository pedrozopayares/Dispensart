<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\LoginAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;

final class LoginController
{
    /**
     * Inicia sesión por cookie HttpOnly. Nunca devuelve token.
     */
    public function __invoke(LoginRequest $request, LoginAction $login): AuthenticatedUserResource
    {
        $user = $login->handle($request->email(), $request->password(), (string) $request->ip(), $request->session());

        return new AuthenticatedUserResource($user);
    }
}
