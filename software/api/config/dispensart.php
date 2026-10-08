<?php

return [
    /*
    | Reloj de negocio (design D6): "hoy" para vencimientos se calcula en esta zona. app.timezone sigue
    | en UTC (timestamps guardados y log JSON).
    */
    'business_timezone' => 'America/Bogota',

    /*
    | Contraseña de los usuarios semilla (design D10). Vacía o ausente = no definida.
    | El valor por defecto es SOLO PARA DESARROLLO LOCAL, protege usuarios sintéticos y nunca se usa
    | con APP_ENV=production.
    */
    'seed_user_password' => env('SEED_USER_PASSWORD'),

    'seed_user_password_dev_default' => 'dispensart-dev-only',
];
