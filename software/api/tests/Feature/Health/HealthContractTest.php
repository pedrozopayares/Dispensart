<?php

// service-health «Contrato OpenAPI de salud y disponibilidad»: lee el openapi.json versionado (el que exporta
// `composer openapi`) y fija lo que declara de GET /health y GET /ready.

/**
 * @return array<string, mixed>
 */
function healthContract(): array
{
    return json_decode((string) file_get_contents(base_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Operación GET de la ruta; la prueba falla nombrando la operación si el contrato no la trae.
 *
 * @return array<string, mixed>
 */
function healthOperation(string $path): array
{
    $operation = healthContract()['paths'][$path]['get'] ?? null;
    if (! is_array($operation)) {
        test()->fail("openapi.json no documenta la operación GET {$path}.");
    }

    return $operation;
}

/**
 * Esquema JSON de la respuesta con el código dado.
 *
 * @param  array<string, mixed>  $operation
 * @return array<string, mixed>
 */
function healthSchema(array $operation, int $status): array
{
    return $operation['responses'][(string) $status]['content']['application/json']['schema'] ?? [];
}

/**
 * Todas las claves `properties` de un esquema, a cualquier profundidad.
 *
 * @param  array<mixed>  $node
 * @return list<string>
 */
function healthPropertyNames(array $node): array
{
    $names = [];
    foreach ($node as $key => $value) {
        if ($key === 'properties' && is_array($value)) {
            $names = [...$names, ...array_map('strval', array_keys($value))];
        }
        if (is_array($value)) {
            $names = [...$names, ...healthPropertyNames($value)];
        }
    }

    return $names;
}

/**
 * Valores bajo claves `example` o `examples`, a cualquier profundidad.
 *
 * @param  array<mixed>  $node
 * @return list<mixed>
 */
function healthExamples(array $node): array
{
    $found = [];
    foreach ($node as $key => $value) {
        if ($key === 'example' || $key === 'examples') {
            $found[] = $value;
        } elseif (is_array($value)) {
            $found = [...$found, ...healthExamples($value)];
        }
    }

    return $found;
}

test('Vivacidad documentada', function () {
    $operation = healthOperation('/health');
    $schema = healthSchema($operation, 200);

    expect(array_keys($operation['responses']))->toBe([200])
        ->and($schema['type'])->toBe('object')
        ->and(array_keys($schema['properties']))->toBe(['status'])
        ->and($schema['properties']['status']['enum'])->toBe(['ok'])
        ->and($schema['required'])->toBe(['status'])
        ->and($operation['responses']['200']['headers'])->toHaveKey('X-Correlation-Id');
});

test('Disponibilidad documentada con su fallo', function () {
    $operation = healthOperation('/ready');

    expect(array_keys($operation['responses']))->toBe([200, 503]);
    foreach ([200 => 'ready', 503 => 'not_ready'] as $status => $readiness) {
        $schema = healthSchema($operation, $status);
        $checks = $schema['properties']['checks'];

        expect($operation['responses'][(string) $status]['headers'])->toHaveKey('X-Correlation-Id')
            ->and(array_keys($schema['properties']))->toBe(['status', 'checks'])
            ->and($schema['required'])->toBe(['status', 'checks'])
            ->and($schema['properties']['status']['enum'])->toContain($readiness)
            ->and(array_keys($checks['properties']))->toBe(['database', 'migrations'])
            ->and($checks['required'])->toBe(['database', 'migrations'])
            ->and($checks['properties']['database']['enum'])->toBe(['ok', 'fail'])
            ->and($checks['properties']['migrations']['enum'])->toBe(['ok', 'pending', 'skipped', 'fail']);
    }
    expect(healthSchema($operation, 200)['properties']['status']['enum'])->toBe(['ready'])
        ->and(healthSchema($operation, 503)['properties']['status']['enum'])->toBe(['not_ready']);
});

test('URL en la raíz del origen', function () {
    $contract = healthContract();
    $documentServers = array_column($contract['servers'], 'url');
    $url = function (string $path) use ($contract, $documentServers): array {
        $servers = isset($contract['paths'][$path]['servers'])
            ? array_column($contract['paths'][$path]['servers'], 'url')
            : $documentServers;

        return array_map(fn (string $server): string => rtrim($server, '/').$path, $servers);
    };

    healthOperation('/health');
    healthOperation('/ready');
    expect($url('/health'))->toBe(['/health'])
        ->and($url('/ready'))->toBe(['/ready']);

    $others = array_diff(array_keys($contract['paths']), ['/health', '/ready']);
    expect(count($others))->toBeGreaterThan(20);
    foreach ($others as $path) {
        expect($url($path))->toBe(['/api'.$path]);
    }
});

test('Operaciones públicas sin seguridad de sesión', function () {
    expect(healthContract())->not->toHaveKey('security');
    foreach (['/health', '/ready'] as $path) {
        $operation = healthOperation($path);
        $headers = array_column(array_filter($operation['parameters'] ?? [], fn (array $p): bool => $p['in'] === 'header'), 'name');

        expect($operation['security'])->toBe([[]])
            ->and($operation['responses'])->not->toHaveKey(401)->not->toHaveKey(419)
            ->and($headers)->not->toContain('X-XSRF-TOKEN');
    }
    // Control positivo: una operación con sesión sí declara la cookie y el 401.
    expect(healthContract()['paths']['/alerts']['get']['security'])->toBe([['sessionCookie' => []]])
        ->and(healthContract()['paths']['/alerts']['get']['responses'])->toHaveKey(401);
});

test('Contrato sin claves internas', function () {
    foreach (['/health', '/ready'] as $path) {
        $responses = healthOperation($path)['responses'];
        $names = healthPropertyNames($responses);

        expect($names)->not->toBe([])
            ->and(array_values(array_diff($names, ['status', 'checks', 'database', 'migrations'])))->toBe([])
            ->and(healthExamples($responses))->toBe([]);
    }
});

test('Salud ausente del contrato', function () {
    $paths = healthContract()['paths'];

    foreach (['/health', '/ready'] as $path) {
        expect(isset($paths[$path]['get']))->toBeTrue("openapi.json no documenta la operación GET {$path}.");
    }
});
