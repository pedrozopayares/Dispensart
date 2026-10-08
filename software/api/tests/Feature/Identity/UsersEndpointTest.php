<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// identity-access "Listado de usuarios" y "Alta de usuarios". Autorización por rol con actingAs, sin Origin.

function validNewUser(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nueva Auxiliar',
        'email' => 'nueva.aux@dispensart.test',
        'password' => 'clave-segura-12',
        'role' => 'auxiliar_farmacia',
    ], $overrides);
}

describe('GET /api/users', function () {
    it('lista a un admin los 5 usuarios semilla por nombre, sin contraseña ni token', function () {
        $this->seed(UserSeeder::class);
        $admin = User::query()->where('email', 'admin@dispensart.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/users')->assertOk();

        expect($response->json('data'))->toHaveCount(5)
            ->and(array_column($response->json('data'), 'name'))
            ->toBe(['Administrador Demo', 'Auditor Demo', 'Auxiliar Demo', 'Médico Demo', 'Regente Demo']);
        foreach ($response->json('data') as $user) {
            expect(array_keys($user))->toBe(['id', 'name', 'email', 'role']);
        }
    });

    it('rechaza con 403 a los demás roles', function (Role $role) {
        $this->actingAs(User::factory()->withRole($role)->create())
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertExactJson(['code' => 'forbidden', 'message' => 'No tienes permiso para realizar esta acción.']);
    })->with('non_admin_roles');

    it('responde 401 sin sesión', function () {
        $this->getJson('/api/users')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });
});

describe('POST /api/users', function () {
    it('crea un usuario con contraseña con hash que luego inicia sesión', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', validNewUser());

        $response->assertCreated()->assertJson(['data' => [
            'name' => 'Nueva Auxiliar',
            'email' => 'nueva.aux@dispensart.test',
            'role' => 'auxiliar_farmacia',
        ]]);
        expect(array_keys($response->json('data')))->toBe(['id', 'name', 'email', 'role'])
            ->and(DB::table('users')->where('email', 'nueva.aux@dispensart.test')->value('password'))
            ->not->toBe('clave-segura-12');

        (new SpaClient($this))->login('nueva.aux@dispensart.test', 'clave-segura-12')
            ->assertOk()
            ->assertJsonPath('data.role', 'auxiliar_farmacia');
    });

    it('guarda el correo en minúsculas', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', validNewUser(['email' => 'Nueva.AUX@Dispensart.test']))
            ->assertCreated()
            ->assertJsonPath('data.email', 'nueva.aux@dispensart.test');
    });

    it('rechaza un correo duplicado sin distinguir mayúsculas', function () {
        User::factory()->medico()->create(['email' => 'medico@dispensart.test']);
        $admin = User::factory()->admin()->create();
        $before = User::count();

        $this->actingAs($admin)
            ->postJson('/api/users', validNewUser(['email' => 'Medico@Dispensart.test']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['email'], responseKey: 'errors');
        expect(User::count())->toBe($before);
    });

    it('rechaza rol inválido o datos incompletos en el campo afectado', function (array $overrides, string $field) {
        $admin = User::factory()->admin()->create();
        $payload = validNewUser($overrides);
        if ($overrides === ['name' => null]) {
            unset($payload['name']);
        }

        $response = $this->actingAs($admin)->postJson('/api/users', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');

        expect(array_keys($response->json('errors')))->toBe([$field])
            ->and($response->json("errors.{$field}.0"))->toBeString()->not->toContain('validation.');
        expect(User::count())->toBe(1);
    })->with([
        'rol superusuario' => [['role' => 'superusuario'], 'role'],
        'sin nombre' => [['name' => null], 'name'],
        'contraseña de 7 caracteres' => [['password' => '1234567'], 'password'],
    ]);

    it('rechaza con 403 a los demás roles y no crea usuario', function (Role $role) {
        $actor = User::factory()->withRole($role)->create();

        $this->actingAs($actor)
            ->postJson('/api/users', validNewUser(['role' => 'admin']))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
        expect(User::count())->toBe(1);
    })->with('non_admin_roles');

    it('responde 401 sin sesión y no crea usuario', function () {
        $this->postJson('/api/users', validNewUser())->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');

        expect(User::count())->toBe(0);
    });
});
