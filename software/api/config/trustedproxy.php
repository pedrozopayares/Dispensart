<?php

/*
| La API corre detrás de dos Nginx (web → api). Se confía en X-Forwarded-For solo desde rangos privados
| para que el limitador de login vea la IP real del cliente sin aceptar cabeceras falsificadas (design D3).
*/
return [
    'proxies' => env('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'),
];
