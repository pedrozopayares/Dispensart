<?php

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// catalog "Consulta/Alta/Modificación de productos"; identity-access "Rol falsificado por el cliente
// ignorado", "Validación con errores por campo" y "Recurso inexistente".

describe('GET /api/products', function () {
    it('devuelve a cualquier rol los 6 productos semilla por nombre, uno de control especial', function (Role $role) {
        $this->seed(ProductSeeder::class);

        $response = $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/products');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $sorted = $names;
        sort($sorted);
        expect($names)->toHaveCount(6)->toBe($sorted)
            ->and(array_filter(array_column($response->json('data'), 'is_controlled')))->toHaveCount(1)
            ->and(array_keys($response->json('data.0')))->toBe(['id', 'code', 'name', 'presentation', 'is_controlled']);
    })->with('all_roles');

    it('devuelve una lista vacía sin productos', function () {
        $this->actingAs(User::factory()->create())->getJson('/api/products')->assertOk()->assertExactJson(['data' => []]);
    });

    it('responde 401 sin sesión', function () {
        $this->getJson('/api/products')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });
});

describe('POST /api/products', function () {
    it('crea un producto de control especial', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', [
                'code' => 'MED-099',
                'name' => 'Hidromorfona 2 mg/mL',
                'presentation' => 'Ampolla 1 mL',
                'is_controlled' => true,
            ])
            ->assertCreated()
            ->assertJson(['data' => [
                'code' => 'MED-099',
                'name' => 'Hidromorfona 2 mg/mL',
                'presentation' => 'Ampolla 1 mL',
                'is_controlled' => true,
            ]]);
    });

    it('usa presentation null e is_controlled false si se omiten', function () {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', ['code' => 'MED-100', 'name' => 'Loratadina 10 mg']);

        $response->assertCreated()
            ->assertJsonPath('data.presentation', null)
            ->assertJsonPath('data.is_controlled', false);
        expect(Product::query()->where('code', 'MED-100')->value('is_controlled'))->toBeFalse();
    });

    it('rechaza datos inválidos por campo, con mensajes en español', function (array $payload, string $field) {
        Product::factory()->create(['code' => 'MED-001']);

        $response = $this->actingAs(User::factory()->admin()->create())->postJson('/api/products', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('message', 'Los datos enviados no son válidos.');

        expect(array_keys($response->json('errors')))->toBe([$field])
            ->and($response->json("errors.{$field}.0"))->toBeString()->not->toContain('validation.')
            ->and(Product::count())->toBe(1);
    })->with([
        'is_controlled quizás' => [['code' => 'MED-101', 'name' => 'X', 'is_controlled' => 'quizás'], 'is_controlled'],
        'sin name' => [['code' => 'MED-101'], 'name'],
        'code existente' => [['code' => 'MED-001', 'name' => 'Duplicado'], 'code'],
    ]);

    it('rechaza sin code con errors.code en español', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/products', ['name' => 'Sin código'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.code.0', 'Este campo es obligatorio.');
    });

    it('rechaza con 403 a los demás roles y no crea producto', function (Role $role) {
        $this->actingAs(User::factory()->withRole($role)->create())
            ->postJson('/api/products', ['code' => 'MED-102', 'name' => 'Intento'])
            ->assertForbidden()
            ->assertExactJson(['code' => 'forbidden', 'message' => 'No tienes permiso para realizar esta acción.']);

        expect(Product::count())->toBe(0);
    })->with('non_admin_roles');

    it('ignora un rol falsificado en el cuerpo o en cabeceras', function () {
        $this->actingAs(User::factory()->auxiliar()->create())
            ->withHeaders(['X-Role' => 'admin'])
            ->postJson('/api/products', ['code' => 'MED-103', 'name' => 'Intento', 'role' => 'admin'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        expect(Product::count())->toBe(0);
    });

    it('responde 401 sin sesión y no crea producto', function () {
        $this->postJson('/api/products', ['code' => 'MED-104', 'name' => 'Intento'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        expect(Product::count())->toBe(0);
    });
});

describe('PATCH /api/products/{id}', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->product = Product::factory()->create([
            'code' => 'MED-001', 'name' => 'Acetaminofén 500 mg', 'presentation' => 'Tableta', 'is_controlled' => false,
        ]);
    });

    it('marca como control especial sin cambiar los demás campos', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/products/{$this->product->id}", ['is_controlled' => true])
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $this->product->id,
                'code' => 'MED-001',
                'name' => 'Acetaminofén 500 mg',
                'presentation' => 'Tableta',
                'is_controlled' => true,
            ]]);
    });

    it('rechaza el código de otro producto sin cambiarlo', function () {
        Product::factory()->create(['code' => 'MED-002']);

        $this->actingAs($this->admin)
            ->patchJson("/api/products/{$this->product->id}", ['code' => 'MED-002'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'], responseKey: 'errors');
        expect($this->product->fresh()?->code)->toBe('MED-001');
    });

    it('responde 404 en español sin nombre de clase a un producto inexistente', function () {
        $response = $this->actingAs($this->admin)->patchJson('/api/products/999999', ['name' => 'X']);

        $response->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => 'El recurso solicitado no existe.']);
        expect((string) $response->getContent())->not->toContain('Product')->not->toContain('App\\');
    });

    it('responde 404 sin 500 a un id que no cabe en bigint', function () {
        $this->actingAs($this->admin)->patchJson('/api/products/9999999999999999999', ['name' => 'X'])
            ->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => 'El recurso solicitado no existe.']);
    });

    it('rechaza con 403 a un auditor sin cambiar el producto', function () {
        $this->product->update(['is_controlled' => true]);

        $this->actingAs(User::factory()->auditor()->create())
            ->patchJson("/api/products/{$this->product->id}", ['is_controlled' => false])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
        expect($this->product->fresh()?->is_controlled)->toBeTrue();
    });
});
