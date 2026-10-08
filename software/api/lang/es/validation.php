<?php

// Mensajes de validación en español, solo de las reglas que usa la API (design D5). Sin paquete externo.
return [
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'required' => 'Este campo es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El valor de :attribute ya está en uso.',

    'attributes' => [
        'code' => 'código',
        'email' => 'correo electrónico',
        'is_controlled' => 'control especial',
        'name' => 'nombre',
        'password' => 'contraseña',
        'presentation' => 'presentación',
        'product_id' => 'producto',
        'role' => 'rol',
    ],
];
