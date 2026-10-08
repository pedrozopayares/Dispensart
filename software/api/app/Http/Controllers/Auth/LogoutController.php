<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\LogoutAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LogoutController
{
    /**
     * Cierra la sesión actual.
     */
    public function __invoke(Request $request, LogoutAction $logout): Response
    {
        $logout->handle($request->session());

        return response()->noContent();
    }
}
