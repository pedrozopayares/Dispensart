<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// identity-access "Inicio de sesión": HTTP real desde /sanctum/csrf-cookie, CSRF activa, sin actingAs.

function sessionCookieName(): string
{
    return (string) config('session.cookie');
}

function invalidCredentialsBody(): array
{
    return ['code' => 'invalid_credentials', 'message' => 'Correo o contraseña incorrectos.'];
}

beforeEach(function () {
    $this->user = User::factory()->regente()->create(['email' => 'regente@dispensart.test', 'name' => 'Regente Prueba']);
    $this->spa = new SpaClient($this);
});

it('abre sesión con credenciales válidas, regenera la sesión y no devuelve token', function () {
    $this->spa->csrfCookie()->assertNoContent();
    $sessionBefore = $this->spa->decryptedCookie(sessionCookieName());

    $response = $this->spa->post('/api/auth/login', ['email' => 'regente@dispensart.test', 'password' => 'password']);

    $response->assertOk()->assertExactJson(['data' => [
        'id' => $this->user->id,
        'name' => 'Regente Prueba',
        'email' => 'regente@dispensart.test',
        'role' => 'regente_farmacia',
        'abilities' => Role::RegenteFarmacia->abilityValues(),
    ]]);
    $sessionCookie = $response->getCookie(sessionCookieName(), decrypt: false);
    expect($sessionCookie)->not->toBeNull()
        ->and($sessionCookie->isHttpOnly())->toBeTrue()
        ->and($this->spa->decryptedCookie(sessionCookieName()))->not->toBe($sessionBefore)
        ->and((string) $response->getContent())->not->toContain('token')
        ->not->toContain('password');

    $this->spa->get('/api/auth/me')->assertOk()->assertJsonPath('data.id', $this->user->id);
});

it('compara el correo sin distinguir mayúsculas', function () {
    User::factory()->admin()->create(['email' => 'admin@dispensart.test']);

    $this->spa->login('ADMIN@Dispensart.test')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@dispensart.test')
        ->assertJsonPath('data.role', 'admin');
});

it('rechaza una contraseña incorrecta sin abrir sesión', function () {
    $this->spa->login('regente@dispensart.test', 'incorrecta-123')
        ->assertStatus(422)
        ->assertExactJson(invalidCredentialsBody());

    $this->spa->get('/api/auth/me')->assertUnauthorized();
});

it('responde igual para un correo inexistente que para una contraseña incorrecta', function () {
    $wrongPassword = $this->spa->login('regente@dispensart.test', 'incorrecta-123');
    $unknownEmail = (new SpaClient($this))->login('nadie@dispensart.test', 'incorrecta-123');

    $unknownEmail->assertStatus(422)->assertExactJson(invalidCredentialsBody());
    expect($unknownEmail->getContent())->toBe($wrongPassword->getContent());
});

it('rechaza datos incompletos o mal formados por campo', function (array $body, array $fields) {
    $this->spa->csrfCookie();

    $response = $this->spa->post('/api/auth/login', $body)
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($fields, responseKey: 'errors');

    expect(array_keys($response->json('errors')))->toEqualCanonicalizing($fields);
})->with([
    'solo email' => [['email' => 'regente@dispensart.test'], ['password']],
    'email sin formato' => [['email' => 'no-es-correo', 'password' => 'password'], ['email']],
    'cuerpo vacío' => [[], ['email', 'password']],
]);

it('bloquea con 429 el sexto intento tras cinco fallos, aun con la contraseña correcta', function () {
    foreach (range(1, 5) as $attempt) {
        $this->spa->login('regente@dispensart.test', 'incorrecta-123')->assertStatus(422);
    }

    $response = $this->spa->login('regente@dispensart.test', 'password');

    $response->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
    $this->spa->get('/api/auth/me')->assertUnauthorized();
});

it('reinicia el contador de fallos tras un inicio de sesión exitoso', function () {
    foreach (range(1, 4) as $attempt) {
        $this->spa->login('regente@dispensart.test', 'incorrecta-123')->assertStatus(422);
    }
    $this->spa->login('regente@dispensart.test', 'password')->assertOk();
    $this->spa->post('/api/auth/logout')->assertNoContent();

    // Sin reinicio, el segundo fallo ya sería el sexto intento contado y respondería 429.
    foreach (range(1, 5) as $attempt) {
        $this->spa->login('regente@dispensart.test', 'incorrecta-123')
            ->assertStatus(422)
            ->assertExactJson(invalidCredentialsBody());
    }
});

it('rechaza con 403 y sin cookie el login desde un origen ajeno a la SPA', function (?string $origin) {
    $foreign = new SpaClient($this, origin: $origin);

    $response = $foreign->post('/api/auth/login', ['email' => 'regente@dispensart.test', 'password' => 'password']);

    $response->assertForbidden()->assertExactJson([
        'code' => 'forbidden',
        'message' => 'No tienes permiso para realizar esta acción.',
    ]);
    expect($response->headers->getCookies())->toBeEmpty();
    $foreign->get('/api/auth/me')->assertUnauthorized();
})->with([
    'origen ajeno' => ['http://evil.test'],
    'sin Origin ni Referer' => [null],
]);

it('responde 401, no 500, ante una cabecera Bearer falsa', function () {
    $this->getJson('/api/auth/me', ['Authorization' => 'Bearer 1|falso'])
        ->assertUnauthorized()
        ->assertExactJson(['code' => 'unauthenticated', 'message' => 'Debes iniciar sesión para continuar.']);
});
