<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

/*
| OpenAPI de la API (design D9). dedoc/scramble es dependencia solo de desarrollo: el contrato se exporta a
| software/api/openapi.json (versionado) con `composer openapi`; la imagen de producción no lo sirve.
*/
return [
    'api_path' => 'api',

    'api_domain' => null,

    'export_path' => 'openapi.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'array',
    ],

    'info' => [
        'version' => '0.1.0',
        'description' => 'API de Dispensart: dispensación de medicamentos, inventario por lote (FEFO) y traslados '
            .'entre bodegas para FARTMAR IPS. Autenticación Sanctum SPA por cookie HttpOnly con token CSRF '
            .'(GET /sanctum/csrf-cookie y cabecera X-XSRF-TOKEN en toda escritura). Sin tokens bearer. '
            .'Todo rechazo responde JSON {code, message[, errors]}; `code` es estable y `message` está en español.',
    ],

    'ui' => [
        'title' => 'Dispensart API',
    ],

    'dev_tools' => [
        'enabled' => false,
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => true,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    'security_strategy' => null,
];
