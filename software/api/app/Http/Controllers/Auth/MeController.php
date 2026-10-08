<?php

namespace App\Http\Controllers\Auth;

use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;

final class MeController
{
    /**
     * Usuario de la sesión con las capacidades de su rol.
     */
    public function __invoke(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user());
    }
}
