<?php

use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// catalog "Consulta de lotes" y "Estado de vencimiento del lote" en la respuesta HTTP.

it('devuelve todos los lotes por vencimiento y luego id', function () {
    $product = Product::factory()->create();
    $later = Lot::factory()->for($product)->create(['expires_on' => '2030-06-30']);
    $sameDayA = Lot::factory()->for($product)->create(['expires_on' => '2030-01-31']);
    $sameDayB = Lot::factory()->create(['expires_on' => '2030-01-31']);

    $response = $this->actingAs(User::factory()->auditor()->create())->getJson('/api/lots');

    $response->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$sameDayA->id, $sameDayB->id, $later->id])
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'product_id', 'lot_code', 'expires_on', 'is_expired'])
        ->and($response->json('data.0.expires_on'))->toBe('2030-01-31');
});

it('filtra por producto', function () {
    $product = Product::factory()->create();
    $lots = Lot::factory()->count(3)->for($product)->create();
    Lot::factory()->count(2)->create();

    $response = $this->actingAs(User::factory()->medico()->create())->getJson("/api/lots?product_id={$product->id}");

    $response->assertOk();
    expect(array_column($response->json('data'), 'id'))->toEqualCanonicalizing($lots->pluck('id')->all());
});

it('devuelve una lista vacía para un producto inexistente', function () {
    Lot::factory()->create();

    $this->actingAs(User::factory()->create())->getJson('/api/lots?product_id=999999')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('rechaza un filtro mal formado', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/lots?product_id=abc')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['product_id'], responseKey: 'errors');
});

it('responde 401 sin sesión', function () {
    $this->getJson('/api/lots')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('calcula is_expired con el día de Bogotá en cada respuesta', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 23:30:00', 'America/Bogota'));
    $product = Product::factory()->create();
    $yesterday = Lot::factory()->for($product)->create(['expires_on' => '2027-03-13']);
    $today = Lot::factory()->for($product)->create(['expires_on' => '2027-03-14']);
    $tomorrow = Lot::factory()->for($product)->create(['expires_on' => '2027-03-15']);
    $viewer = User::factory()->create();

    $first = collect($this->actingAs($viewer)->getJson('/api/lots')->assertOk()->json('data'))->pluck('is_expired', 'id');
    expect($first->all())->toBe([$yesterday->id => true, $today->id => true, $tomorrow->id => false]);

    // Cambio de día sin escritura: el lote de mañana vence solo por el paso del tiempo.
    $this->travelTo(CarbonImmutable::parse('2027-03-15 00:00:01', 'America/Bogota'));
    $second = collect($this->actingAs($viewer)->getJson('/api/lots')->assertOk()->json('data'))->pluck('is_expired', 'id');
    expect($second[$tomorrow->id])->toBeTrue();
});
