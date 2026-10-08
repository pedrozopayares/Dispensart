<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// identity-access "Cierre de sesión".

it('cierra la sesión: la cookie anterior deja de autenticar y el token CSRF cambia', function () {
    $spa = new SpaClient($this);
    $spa->loginAs(User::factory()->auxiliar()->create());
    $cookiesBefore = $spa->cookies();
    $xsrfBefore = $spa->decryptedCookie('XSRF-TOKEN');

    $spa->post('/api/auth/logout')->assertNoContent();

    expect($spa->decryptedCookie('XSRF-TOKEN'))->not->toBe($xsrfBefore);
    $spa->useCookies($cookiesBefore)->get('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('responde 401 al cerrar sin sesión', function () {
    $spa = new SpaClient($this);
    $spa->csrfCookie();

    $spa->post('/api/auth/logout')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('responde 401, no 500, al repetir el cierre', function () {
    $spa = new SpaClient($this);
    $spa->loginAs(User::factory()->auxiliar()->create());
    $spa->post('/api/auth/logout')->assertNoContent();

    $spa->post('/api/auth/logout')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
