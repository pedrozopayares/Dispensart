<?php

use App\Enums\Role;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// patients "Datos del paciente fuera de logs y rechazos" y "Rutas de pacientes registradas por patrón": el log
// real (formateador y redacción incluidos) capturado en un archivo y barrido como texto (RN-10, design D9).

beforeEach(function () {
    $this->logPath = captureLog();
    $this->ana = Patient::factory()->create([
        'id' => 4242, 'document_number' => '9999010001', 'full_name' => 'Ana Sintética Pérez',
        'phone' => '3000000012', 'birth_date' => '1985-03-12',
    ]);
});

/**
 * Valores de `$needles` que aparecen en el log capturado, crudo y con escapes JSON de unicode.
 *
 * @param  list<string>  $needles
 * @return list<string>
 */
function logHits(string $logPath, array $needles): array
{
    $raw = (string) file_get_contents($logPath);
    $unescaped = json_encode(array_map(fn (array $line) => $line, logLines($logPath)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return array_values(array_filter($needles, fn (string $needle) => str_contains($raw, $needle) || str_contains((string) $unescaped, $needle)));
}

/**
 * @return array<string, mixed>
 */
function closingLine(string $logPath): array
{
    $closing = array_values(array_filter(logLines($logPath), fn (array $line) => $line['message'] === 'request.completed'));
    expect($closing)->toHaveCount(1);

    return $closing[0];
}

const PATIENT_PII = ['9999010001', 'Ana Sintética Pérez', 'Sintética', '3000000012', '1985-03-12'];

it('no deja el documento de los valores enlazados en el cuerpo ni en el log de una excepción de base de datos', function () {
    // Fallo forzado sin costura en producción: DDL dentro de la transacción de la prueba (design D10).
    DB::statement('ALTER TABLE patients RENAME COLUMN full_name TO full_name_forzado');

    $response = $this->actingAs(User::factory()->auxiliar()->create())
        ->withHeader('X-Correlation-Id', 'traza-pii-sql')
        ->getJson('/api/patients?q=9999010001');

    $response->assertStatus(500)->assertExactJson(['code' => 'server_error', 'message' => __('errors.server_error')]);
    $errors = array_values(array_filter(logLines($this->logPath), fn (array $line) => $line['level'] === 'error'));
    expect($errors)->toHaveCount(1)
        ->and($errors[0]['correlation_id'])->toBe('traza-pii-sql')
        ->and($errors[0]['context']['code'])->toBe('42703')
        ->and(logHits($this->logPath, ['9999010001']))->toBe([])
        ->and((string) $response->getContent())->not->toContain('9999010001');

    // Control positivo del barrido: una línea plantada con el documento sí se detecta.
    Log::warning('documento plantado 9999010001');
    expect(logHits($this->logPath, ['9999010001']))->toBe(['9999010001']);
});

it('no escribe datos personales en el log en una búsqueda y una ficha normales', function () {
    $user = User::factory()->auxiliar()->create();

    $this->actingAs($user)->getJson('/api/patients?q=Sint')->assertOk();
    $this->actingAs($user)->getJson('/api/patients/4242')->assertOk();

    // Control positivo: el log sí registró ambas peticiones.
    expect(array_column(array_column(logLines($this->logPath), 'context'), 'path'))
        ->toBe(['/api/patients', '/api/patients/{patient}'])
        ->and(logHits($this->logPath, [...PATIENT_PII, 'Sint', 'q=']))->toBe([]);
});

it('no deja en el cuerpo ni en el log una excepción con nombre y documento en su mensaje', function () {
    DB::unprepared(<<<'SQL'
        CREATE FUNCTION test_fail_with_pii() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN RAISE EXCEPTION 'Fallo con Ana Sintética Pérez 9999010001'; END; $$;
        CREATE TRIGGER test_fail_with_pii BEFORE INSERT ON patient_access_logs
            FOR EACH ROW EXECUTE FUNCTION test_fail_with_pii();
        SQL);

    $response = $this->actingAs(User::factory()->auxiliar()->create())->getJson('/api/patients/4242');

    $response->assertStatus(500)->assertJsonPath('code', 'server_error');
    expect(logHits($this->logPath, ['Ana Sintética Pérez', '9999010001']))->toBe([])
        ->and((string) $response->getContent())->not->toContain('Ana')->not->toContain('9999010001');
    $errors = array_values(array_filter(logLines($this->logPath), fn (array $line) => $line['level'] === 'error'));
    expect($errors)->toHaveCount(1); // control positivo: el fallo sí quedó registrado (sin su mensaje)

    Log::error('plantado: Ana Sintética Pérez');
    expect(logHits($this->logPath, ['Ana Sintética Pérez']))->toBe(['Ana Sintética Pérez']);
});

it('registra la ficha por el patrón de la ruta, sin el id de la URL', function () {
    $this->actingAs(User::factory()->auxiliar()->create())->getJson('/api/patients/4242')->assertOk();

    expect(closingLine($this->logPath)['context']['path'])->toBe('/api/patients/{patient}')
        ->and(logHits($this->logPath, ['/api/patients/4242', '4242']))->toBe([]);
});

it('responde 404 a un paciente inexistente sin su id ni la clase del modelo en el log', function () {
    $this->actingAs(User::factory()->withRole(Role::RegenteFarmacia)->create())->getJson('/api/patients/987654')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    expect(closingLine($this->logPath)['context']['path'])->toBe('/api/patients/{patient}')
        ->and(logHits($this->logPath, ['987654', 'App\\Models\\Patient', 'Models\\\\Patient', 'No query results']))->toBe([]);
});

it('registra unmatched para una ruta inexistente con identificador', function () {
    $this->actingAs(User::factory()->auxiliar()->create())->getJson('/api/patients/987654/historial')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    expect(closingLine($this->logPath)['context']['path'])->toBe('unmatched')
        ->and(logHits($this->logPath, ['987654']))->toBe([]);
});

it('registra /ready sin cambio', function () {
    $this->get('/ready');

    expect(closingLine($this->logPath)['context']['path'])->toBe('/ready');
});
