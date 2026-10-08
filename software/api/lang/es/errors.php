<?php

// Mensajes de error de la API para el usuario. La clave `code` de la respuesta es el contrato con el frontend.
return [
    'unauthenticated' => 'Debes iniciar sesión para continuar.',
    'forbidden' => 'No tienes permiso para realizar esta acción.',
    'not_found' => 'El recurso solicitado no existe.',
    'method_not_allowed' => 'El método HTTP no está permitido para este recurso.',
    'csrf_token_mismatch' => 'La sesión de seguridad expiró. Recarga la página e intenta de nuevo.',
    'validation_failed' => 'Los datos enviados no son válidos.',
    'invalid_credentials' => 'Correo o contraseña incorrectos.',
    'insufficient_stock' => 'No hay existencias suficientes para esta operación.',
    'lot_expired' => 'El lote está vencido: no admite ingreso de unidades.',
    'too_many_attempts' => 'Demasiados intentos. Espera un momento antes de volver a intentar.',
    'http_error' => 'La solicitud no pudo procesarse.',
    'server_error' => 'Ocurrió un error interno. Informe a soporte el identificador de correlación.',
];
