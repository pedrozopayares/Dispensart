<?php

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

// audit-trail "Bitácora de alta de usuarios" (S10): POST /api/users por HTTP real.
// DatabaseMigrations y no RefreshDatabase: el fallo forzado debe observar lo que de verdad queda confirmado.
// Dentro de la transacción de RefreshDatabase, una fila escrita fuera de la transacción del alta abortaría la
// transacción de la prueba y ninguna aserción posterior podría leer el estado.

uses(DatabaseMigrations::class);

const NEW_USER_NAME = 'Nueva Auxiliar Sintética';
const NEW_USER_EMAIL = 'nueva.aux@dispensart.test';
const NEW_USER_PASSWORD = 'Clave-Sintetica-456';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    // El disparador de fallo forzado cae con la tabla en el rollback; la función se borra aquí.
    DB::unprepared('DROP FUNCTION IF EXISTS test_fail_user_created() CASCADE');
});

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function auditedNewUser(array $overrides = []): array
{
    return ['name' => NEW_USER_NAME, 'email' => NEW_USER_EMAIL, 'password' => NEW_USER_PASSWORD, 'role' => 'auxiliar_farmacia', ...$overrides];
}

/**
 * Serialización completa de una fila, sin escapar acentos ni barras: lo que se busca aparece tal cual.
 *
 * @param  array<string, mixed>  $attributes
 */
function rawJson(array $attributes): string
{
    return (string) json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

test('Alta registrada: una fila user.created con el admin como actor y el usuario creado como objeto', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/users', auditedNewUser())->assertCreated();

    $row = AuditEvent::sole();
    $createdId = $response->json('data.id');
    expect($createdId)->not->toBe($this->admin->id)
        ->and([$row->action->value, $row->actor_id, $row->subject_type, $row->subject_id, $row->details])
        ->toBe(['user.created', $this->admin->id, 'user', $createdId, []])
        ->and($row->correlation_id)->toBe($response->headers->get('X-Correlation-Id'))->not->toBeNull()
        ->and($row->created_at)->not->toBeNull();
});

test('Sin datos personales ni secretos en la fila: ni nombre, ni correo, ni contraseña, ni hash', function () {
    $this->actingAs($this->admin)->postJson('/api/users', auditedNewUser())->assertCreated();

    $user = (array) DB::table('users')->where('email', NEW_USER_EMAIL)->sole();
    $row = rawJson(AuditEvent::sole()->getAttributes());
    $forbidden = [NEW_USER_NAME, NEW_USER_EMAIL, NEW_USER_PASSWORD, (string) $user['password']];
    // Control positivo: la misma búsqueda encuentra los cuatro valores en la fila del usuario (el hash, en vez de la contraseña).
    foreach ([NEW_USER_NAME, NEW_USER_EMAIL, (string) $user['password']] as $value) {
        expect(rawJson($user))->toContain($value);
    }
    foreach ($forbidden as $value) {
        expect($row)->not->toContain($value);
    }
});

test('Fallo al registrar revierte el alta: 500 server_error y ningún usuario nuevo', function () {
    DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION test_fail_user_created() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN RAISE EXCEPTION 'fallo forzado de la bitácora'; END; $$;
        CREATE TRIGGER test_fail_user_created BEFORE INSERT ON audit_events
            FOR EACH ROW WHEN (NEW.action = 'user.created') EXECUTE FUNCTION test_fail_user_created();
        SQL);
    $users = User::count();

    $this->actingAs($this->admin)->postJson('/api/users', auditedNewUser())
        ->assertStatus(500)
        ->assertExactJson(['code' => 'server_error', 'message' => __('errors.server_error')]);

    expect(User::where('email', NEW_USER_EMAIL)->exists())->toBeFalse()
        ->and(User::count())->toBe($users)
        ->and(AuditEvent::count())->toBe(0);
});

test('Alta rechazada sin fila: 401, 422 correo duplicado, 422 rol inválido y 403 de otro rol', function () {
    User::factory()->medico()->create(['email' => 'medico@dispensart.test']);
    $auditor = User::factory()->auditor()->create();

    // Sin sesión primero: actingAs deja la sesión puesta para las peticiones siguientes.
    $this->postJson('/api/users', auditedNewUser())->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    expect(AuditEvent::count())->toBe(0);

    $this->actingAs($this->admin)->postJson('/api/users', auditedNewUser(['email' => 'Medico@Dispensart.test']))
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors(['email'], responseKey: 'errors');
    expect(AuditEvent::count())->toBe(0);

    $this->actingAs($this->admin)->postJson('/api/users', auditedNewUser(['role' => 'superusuario']))
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors(['role'], responseKey: 'errors');
    expect(AuditEvent::count())->toBe(0);

    $this->actingAs($auditor)->postJson('/api/users', auditedNewUser())->assertForbidden()->assertJsonPath('code', 'forbidden');
    expect(AuditEvent::count())->toBe(0)
        ->and(User::where('email', NEW_USER_EMAIL)->exists())->toBeFalse();
});
