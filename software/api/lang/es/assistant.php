<?php

// Textos del asistente de inventario (S7). `messages` son las respuestas fijas por `outcome`; el resto son las
// plantillas con que el servidor compone `answer` desde los resultados de las herramientas (design D8).
return [
    'messages' => [
        'out_of_scope' => 'Solo puedo responder consultas de inventario: existencias, lotes por vencer, productos bajo el stock mínimo y estado de traslados.',
        'no_results' => 'No encontré resultados para esa consulta.',
        'not_permitted' => 'Tu rol no tiene permiso para consultar esa información.',
        'unknown' => 'No sé responder esa pregunta con la información disponible.',
    ],

    'expiring' => [
        'title' => 'Lotes con existencia que vencen en :days días o menos:',
        'line' => '- :product, lote :lot en :warehouse: :quantity unidades, vence el :date',
        'expired' => ' (vencido)',
    ],

    'stock' => [
        'title' => 'Existencias:',
        'line' => '- :product en :warehouse: :available unidades disponibles (sin contar lotes vencidos).',
        'lot' => '  · lote :lot: :quantity unidades, vence el :date',
        'expired' => ' (vencido)',
    ],

    'low_stock' => [
        'title' => 'Productos bajo el stock mínimo:',
        'line' => '- :product en :warehouse: :available disponibles, mínimo :minimum.',
    ],

    'transfer' => [
        'detail' => 'Traslado #:id: :status, de :origin a :destination.',
        'line' => '- :product, lote :lot: enviado :sent, recibido :received.',
        'not_received' => 'sin recibir',
        'pending_title' => 'Discrepancias pendientes:',
        'pending' => '- :product, lote :lot: faltan :shortage unidades.',
        'counts_title' => 'Traslados por estado:',
        'count' => '- :status: :count',
    ],

    'status' => [
        'BORRADOR' => 'borrador',
        'SOLICITADO' => 'solicitado',
        'APROBADO' => 'aprobado',
        'EN_TRANSITO' => 'en tránsito',
        'RECIBIDO' => 'recibido',
        'RECIBIDO_PARCIAL' => 'recibido parcialmente',
        'ANULADO' => 'anulado',
    ],

    'eval' => [
        'pass' => 'ACIERTO',
        'fail' => 'FALLO',
        'unavailable' => 'asistente no disponible',
        'total' => 'Aciertos: :passed/:total',
        'file_missing' => 'No se encontró el conjunto de evaluación en :path.',
        'file_invalid' => 'El conjunto de evaluación no es JSON válido.',
        'set_invalid' => 'Conjunto de evaluación inválido: :reason',
        'too_few' => 'el conjunto tiene :count entradas y necesita al menos :minimum.',
        'database_unavailable' => 'No se pudo preparar la base de evaluación desechable: el usuario de la base no puede crearla o no está disponible.',
        'database_name_clash' => 'La base de evaluación coincide con la base operativa: no se evalúa.',
    ],
];
