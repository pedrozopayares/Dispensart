<?php

use App\Http\Middleware\ValidateCsrfToken;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
| Sanctum solo en modo SPA por cookie (ADR-0001, design D1): sin tokens bearer ni tabla de tokens.
*/
return [
    // Orígenes de la SPA con sesión. Por defecto el Nginx de web en el puerto publicado (8090).
    'stateful' => array_filter(array_map(
        'trim',
        explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', 'localhost:8090,127.0.0.1:8090')),
    )),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => '',

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        // CSRF sin el atajo de pruebas del framework (design D2).
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
