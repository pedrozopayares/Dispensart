<?php

// Mensajes de validación en español, solo de las reglas que usa la API (design D5). Sin paquete externo.
return [
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'array' => 'El campo :attribute debe ser una lista.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
    ],
    'date_format' => 'El campo :attribute debe tener el formato :format.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'exists' => 'El valor de :attribute no existe.',
    'list' => 'El campo :attribute debe ser una lista.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elemento.',
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
        'q' => 'término de búsqueda',
        'patient_id' => 'paciente',
        'valid_until' => 'vigencia',
        'items' => 'ítems',
        'items.*.product_id' => 'producto',
        'items.*.quantity' => 'cantidad',
        'items.*.prescription_item_id' => 'ítem de la prescripción',
        'prescription_id' => 'prescripción',
        'warehouse_id' => 'bodega',
        'authorizer_email' => 'correo del autorizador',
        'authorizer_password' => 'contraseña del autorizador',
    ],
];
