<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// identity-access "Usuario actual" y "401 en JSON aunque falte Accept".

it('devuelve el usuario de la sesión con las capacidades de su rol', function () {
    $user = User::factory()->regente()->create(['name' => 'Regente Uno', 'email' => 'regente.uno@dispensart.test']);
    $spa = new SpaClient($this);
    $spa->loginAs($user);

    $spa->get('/api/auth/me')->assertOk()->assertExactJson(['data' => [
        'id' => $user->id,
        'name' => 'Regente Uno',
        'email' => 'regente.uno@dispensart.test',
        'role' => 'regente_farmacia',
        'abilities' => Role::RegenteFarmacia->abilityValues(),
    ]]);
});

it('responde 401 sin sesión', function () {
    $this->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['code' => 'unauthenticated', 'message' => 'Debes iniciar sesión para continuar.']);
});

it('responde 401 en JSON aunque falte Accept, sin redirección', function () {
    $response = $this->get('/api/auth/me');

    $response->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['code' => 'unauthenticated', 'message' => 'Debes iniciar sesión para continuar.']);
    expect($response->headers->has('Location'))->toBeFalse();
});
