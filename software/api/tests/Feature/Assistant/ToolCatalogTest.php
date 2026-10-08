<?php

use App\Enums\TransferStatus;
use App\Services\Assistant\Tools\ToolArgumentValidator;
use App\Services\Assistant\Tools\ToolRegistry;

// inventory-assistant «Catálogo cerrado de herramientas de solo lectura» a nivel de catálogo y validador
// (design D3). Las mismas reglas pasan por la ruta en AssistantEndpointTest y por el orquestador.

function schemaOf(string $tool): array
{
    return app(ToolRegistry::class)->find($tool)->parameters();
}

test('Catálogo ofrecido al modelo: exactamente las 4 herramientas, cada una con su esquema cerrado', function () {
    $definitions = app(ToolRegistry::class)->definitions();

    expect(array_map(fn ($d) => $d->name, $definitions))
        ->toBe(['find_expiring_lots', 'get_stock', 'get_low_stock_alerts', 'get_transfer_status']);
    foreach ($definitions as $definition) {
        expect($definition->parameters['type'])->toBe('object')
            ->and($definition->parameters['additionalProperties'])->toBeFalse()
            ->and($definition->description)->not->toBe('');
    }
    expect(array_keys($definitions[0]->parameters['properties']))->toBe(['days', 'product', 'warehouse'])
        ->and(array_keys($definitions[1]->parameters['properties']))->toBe(['product', 'warehouse'])
        ->and(array_keys($definitions[2]->parameters['properties']))->toBe(['warehouse'])
        ->and(array_keys($definitions[3]->parameters['properties']))->toBe(['transfer_id', 'status', 'warehouse'])
        ->and($definitions[3]->parameters['properties']['status']['enum'])->toBe(TransferStatus::values());
});

test('Herramienta fuera del catálogo: no resuelve a nada', function (string $name) {
    expect(app(ToolRegistry::class)->find($name))->toBeNull()
        // Control positivo: un nombre del catálogo sí resuelve.
        ->and(app(ToolRegistry::class)->find('get_stock'))->not->toBeNull();
})->with(['approve_transfer', 'run_sql', 'get_patient', 'GET_STOCK', '']);

test('Argumentos fuera de esquema: argumento adicional o tipo incorrecto invalidan la llamada', function (array|string $arguments) {
    expect(app(ToolArgumentValidator::class)->validate(schemaOf('get_stock'), $arguments))->toBeNull();
})->with([
    'sql' => [['product' => 'acetaminofen', 'sql' => 'DELETE FROM stocks']],
    'user_id' => [['user_id' => 1]],
    'role' => [['role' => 'regente_farmacia']],
    'bodega numérica' => [['warehouse' => 7]],
    'texto que no es JSON' => ['{no es json'],
    'lista en lugar de objeto' => [['farmacia central']],
    'producto demasiado largo' => [['product' => str_repeat('a', 101)]],
]);

test('Argumentos válidos se aceptan, también como texto JSON', function () {
    $validator = app(ToolArgumentValidator::class);

    expect($validator->validate(schemaOf('get_stock'), ['product' => 'acetaminofen', 'warehouse' => 'farmacia central']))
        ->toBe(['product' => 'acetaminofen', 'warehouse' => 'farmacia central'])
        ->and($validator->validate(schemaOf('get_stock'), '{"warehouse":"farmacia central"}'))->toBe(['warehouse' => 'farmacia central'])
        ->and($validator->validate(schemaOf('get_stock'), []))->toBe([])
        ->and($validator->validate(schemaOf('get_stock'), ''))->toBe([])
        ->and($validator->validate(schemaOf('get_transfer_status'), ['status' => 'EN_TRANSITO']))->toBe(['status' => 'EN_TRANSITO'])
        ->and($validator->validate(schemaOf('get_transfer_status'), ['status' => 'en_transito']))->toBeNull()
        ->and($validator->validate(schemaOf('get_transfer_status'), ['transfer_id' => 0]))->toBeNull();
});

test('Plazo fuera de rango: days fuera de 1–365 o no entero es inválido', function (mixed $days, bool $valid) {
    $result = app(ToolArgumentValidator::class)->validate(schemaOf('find_expiring_lots'), ['days' => $days]);

    expect($result === null)->toBe(! $valid);
})->with([
    '5000' => [5000, false],
    '0' => [0, false],
    '366' => [366, false],
    'texto "60"' => ['60', false],
    'decimal' => [60.0, false],
    '1' => [1, true],
    '365' => [365, true],
]);

test('Rol pedido por el modelo ignorado: role no es argumento de ninguna herramienta', function () {
    $registry = app(ToolRegistry::class);
    foreach ($registry->names() as $name) {
        expect(app(ToolArgumentValidator::class)->validate($registry->find($name)->parameters(), ['role' => 'regente_farmacia']))->toBeNull();
    }
});
