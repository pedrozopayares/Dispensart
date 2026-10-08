<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// catalog "Consulta/Alta/Modificación de bodegas"; identity-access "Rechazo por permisos" y
// "Cambio de rol en la base vigente en la petición siguiente".

describe('GET /api/warehouses', function () {
    it('devuelve a cualquier rol las 3 bodegas semilla ordenadas por nombre', function (Role $role) {
        $this->seed(WarehouseSeeder::class);

        $response = $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/warehouses');

        $response->assertOk();
        expect(array_column($response->json('data'), 'name'))
            ->toBe(['Bodega Hospitalización', 'Farmacia Central', 'Farmacia Urgencias'])
            ->and(array_keys($response->json('data.0')))->toBe(['id', 'code', 'name']);
    })->with('all_roles');

    it('devuelve una lista vacía sin bodegas', function () {
        $this->actingAs(User::factory()->create())->getJson('/api/warehouses')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    });

    it('responde 401 sin sesión', function () {
        $this->getJson('/api/warehouses')
            ->assertUnauthorized()
            ->assertExactJson(['code' => 'unauthenticated', 'message' => 'Debes iniciar sesión para continuar.']);
    });
});

describe('POST /api/warehouses', function () {
    it('crea una bodega como admin', function () {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/warehouses', ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa']);

        $response->assertCreated()->assertJson(['data' => ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa']]);
        expect($response->json('data.id'))->toBeInt();
        $this->assertDatabaseHas('warehouses', ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa']);
    });

    it('rechaza código o nombre duplicado en el campo duplicado', function (array $payload, string $field) {
        Warehouse::factory()->create(['code' => 'FC', 'name' => 'Farmacia Central']);

        $response = $this->actingAs(User::factory()->admin()->create())->postJson('/api/warehouses', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');

        expect(array_keys($response->json('errors')))->toBe([$field])
            ->and(Warehouse::count())->toBe(1);
    })->with([
        'código' => [['code' => 'FC', 'name' => 'Otra'], 'code'],
        'nombre' => [['code' => 'FX', 'name' => 'Farmacia Central'], 'name'],
    ]);

    it('rechaza datos incompletos o demasiado largos', function (array $payload, string $field) {
        $response = $this->actingAs(User::factory()->admin()->create())->postJson('/api/warehouses', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');

        expect(array_keys($response->json('errors')))->toBe([$field])
            ->and(Warehouse::count())->toBe(0);
    })->with([
        'sin nombre' => [['code' => 'FC2'], 'name'],
        'código de 21 caracteres' => [['code' => str_repeat('X', 21), 'name' => 'Larga'], 'code'],
    ]);

    it('rechaza con 403 a los demás roles con el cuerpo exacto y sin crear', function (Role $role) {
        $this->actingAs(User::factory()->withRole($role)->create())
            ->postJson('/api/warehouses', ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa'])
            ->assertForbidden()
            ->assertExactJson(['code' => 'forbidden', 'message' => 'No tienes permiso para realizar esta acción.']);

        expect(Warehouse::count())->toBe(0);
    })->with('non_admin_roles');

    it('responde 401 sin sesión y no crea bodega', function () {
        $this->postJson('/api/warehouses', ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        expect(Warehouse::count())->toBe(0);
    });

    it('aplica en la petición siguiente un cambio de rol hecho en la base, sin cerrar la sesión', function () {
        $spa = new SpaClient($this);
        $admin = User::factory()->admin()->create();
        $spa->loginAs($admin);
        // Control positivo: con rol admin la escritura pasa.
        $spa->post('/api/warehouses', ['code' => 'FC2', 'name' => 'Farmacia Consulta Externa'])->assertCreated();

        DB::table('users')->where('id', $admin->id)->update(['role' => 'auditor']);

        $spa->post('/api/warehouses', ['code' => 'FC3', 'name' => 'Farmacia Tercera'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
        $this->assertDatabaseMissing('warehouses', ['code' => 'FC3']);
        $spa->get('/api/auth/me')->assertOk()->assertJsonPath('data.role', 'auditor');
    });
});

describe('PATCH /api/warehouses/{id}', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->warehouse = Warehouse::factory()->create(['code' => 'FC', 'name' => 'Farmacia Central']);
    });

    it('cambia solo el nombre y conserva el código', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/warehouses/{$this->warehouse->id}", ['name' => 'Farmacia Central Norte'])
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $this->warehouse->id, 'code' => 'FC', 'name' => 'Farmacia Central Norte']]);
    });

    it('permite conservar su propio código', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/warehouses/{$this->warehouse->id}", ['code' => 'FC', 'name' => 'Farmacia Central'])
            ->assertOk()
            ->assertJsonPath('data.code', 'FC');
    });

    it('devuelve la bodega intacta con un cuerpo vacío', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/warehouses/{$this->warehouse->id}", [])
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $this->warehouse->id, 'code' => 'FC', 'name' => 'Farmacia Central']]);
    });

    it('rechaza el código de otra bodega sin cambiarla', function () {
        Warehouse::factory()->create(['code' => 'FU', 'name' => 'Farmacia Urgencias']);

        $this->actingAs($this->admin)
            ->patchJson("/api/warehouses/{$this->warehouse->id}", ['code' => 'FU'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['code'], responseKey: 'errors');
        expect($this->warehouse->fresh()?->code)->toBe('FC');
    });

    it('responde 404 a una bodega inexistente', function () {
        $this->actingAs($this->admin)
            ->patchJson('/api/warehouses/999999', ['name' => 'X'])
            ->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => 'El recurso solicitado no existe.']);
    });

    it('responde 404 sin 500 a un id que no cabe en bigint', function () {
        $this->actingAs($this->admin)
            ->patchJson('/api/warehouses/9999999999999999999', ['name' => 'X'])
            ->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => 'El recurso solicitado no existe.']);
    });

    it('rechaza con 403 a un regente sin cambiar la bodega', function () {
        $this->actingAs(User::factory()->regente()->create())
            ->patchJson("/api/warehouses/{$this->warehouse->id}", ['name' => 'Intento'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
        expect($this->warehouse->fresh()?->name)->toBe('Farmacia Central');
    });
});
