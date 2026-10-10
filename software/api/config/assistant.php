<?php

return [
    /*
    | Proveedor del modelo (design D2): `mock` (por defecto, también con la variable vacía; determinista, sin red)
    | u `ollama` (local, sin llave ni costo). Otro valor responde 503 en el asistente sin afectar otras rutas.
    */
    'provider' => env('AI_PROVIDER') ?: 'mock',

    // Ollama del anfitrión (design D11). Sin servicio en compose ni en CI.
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:3b'),
        'timeout' => (float) env('OLLAMA_TIMEOUT', 30),
    ],

    // Modelos elegibles por pregunta (S15, design D2 y D3): descubrimiento en Ollama con plazo total corto y caché
    // corta de solo nombres en un almacén que no es la base (`file` por defecto; `array` en las pruebas).
    'models' => [
        'budget_seconds' => 2,
        'cache_ttl_seconds' => 30,
        'cache_store' => env('ASSISTANT_MODELS_CACHE_STORE', 'file'),
    ],

    // Plazo total de una pregunta, por debajo del proxy_read_timeout de 60 s del Nginx de web (design D7).
    'deadline_seconds' => 50,

    // Ciclo acotado (inventory-assistant «Ciclo de herramientas acotado»).
    'max_tool_calls' => 4,
    'max_rounds' => 5,

    // Preguntas por minuto por usuario en POST /api/assistant/ask (design D12).
    'rate_per_minute' => 20,
];
