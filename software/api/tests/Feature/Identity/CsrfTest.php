<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// identity-access "Token CSRF para la SPA": verificación real, sin el atajo de pruebas del framework.

it('emite la cookie XSRF-TOKEN legible por la SPA con un 204', function () {
    $response = (new SpaClient($this))->csrfCookie();

    $response->assertNoContent();
    $xsrf = $response->getCookie('XSRF-TOKEN', decrypt: false);
    $session = $response->getCookie((string) config('session.cookie'), decrypt: false);
    expect($xsrf)->not->toBeNull()
        ->and($xsrf->isHttpOnly())->toBeFalse()
        ->and($session?->isHttpOnly())->toBeTrue()
        ->and($session?->getSameSite())->toBe('lax');
});

it('rechaza con 419 el login sin X-XSRF-TOKEN y no abre sesión', function () {
    User::factory()->admin()->create(['email' => 'admin@dispensart.test']);
    $spa = new SpaClient($this);
    $spa->csrfCookie();

    $spa->post('/api/auth/login', ['email' => 'admin@dispensart.test', 'password' => 'password'], withXsrf: false)
        ->assertStatus(419)
        ->assertExactJson([
            'code' => 'csrf_token_mismatch',
            'message' => 'La sesión de seguridad expiró. Recarga la página e intenta de nuevo.',
        ]);

    $spa->get('/api/auth/me')->assertUnauthorized();
    // Control positivo: la misma petición con el token pasa.
    $spa->post('/api/auth/login', ['email' => 'admin@dispensart.test', 'password' => 'password'])->assertOk();
});

it('rechaza con 419 una escritura con token de otra sesión, sin efecto y sin cerrar la sesión', function () {
    $admin = User::factory()->admin()->create();
    $spa = new SpaClient($this);
    $spa->loginAs($admin);
    $other = new SpaClient($this);
    $other->csrfCookie();

    $spa->post('/api/warehouses', ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa'], [
        'X-XSRF-TOKEN' => (string) $other->cookie('XSRF-TOKEN'),
    ], withXsrf: false)->assertStatus(419)->assertJsonPath('code', 'csrf_token_mismatch');

    $this->assertDatabaseMissing('warehouses', ['code' => 'FC2']);
    $spa->get('/api/auth/me')->assertOk()->assertJsonPath('data.id', $admin->id);
    // Control positivo: con su propio token la escritura pasa.
    $spa->post('/api/warehouses', ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa'])->assertCreated();
});

it('permite lecturas con sesión sin X-XSRF-TOKEN', function () {
    $user = User::factory()->medico()->create();
    $spa = new SpaClient($this);
    $spa->loginAs($user);

    $spa->get('/api/auth/me')->assertOk()->assertJsonPath('data.role', 'medico');
});
