<?php

use App\Models\Dispensation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// dispensation "Idempotencia de la dispensación" (RN-09) y audit-trail "Reintento idempotente sin filas
// nuevas": POST /api/dispensations por HTTP real, secuencial. Las carreras están en DispensationRaceTest.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
    $this->stock = lotStock($this->warehouse, $this->product, 30, 20);
    $this->prescription = prescriptionWith([[$this->product, 10]]);
    $this->key = newIdempotencyKey();
});

it('repite la respuesta original byte a byte con Idempotent-Replayed y sin efectos nuevos', function () {
    $body = dispensationBody($this->prescription, $this->warehouse, [[$this->product, 3]]);
    $first = dispense($this->auxiliar, $body, $this->key)->assertCreated();
    $after = dispensationState();

    $retry = dispense($this->auxiliar, $body, $this->key);

    $retry->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect($first->headers->has('Idempotent-Replayed'))->toBeFalse()
        ->and($retry->getContent())->toBe($first->getContent())
        ->and(dispensationState())->toBe($after)
        ->and($this->stock->fresh()?->quantity)->toBe(17);
});

it('repite la respuesta original aunque la prescripción ya esté agotada', function () {
    $body = dispensationBody($this->prescription, $this->warehouse, [[$this->product, 10]]);
    $first = dispense($this->auxiliar, $body, $this->key)->assertCreated();
    $after = dispensationState();

    $retry = dispense($this->auxiliar, $body, $this->key);

    $retry->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect($retry->getContent())->toBe($first->getContent())
        ->and(dispensationState())->toBe($after);
    // Control positivo: con otra clave la misma petición sí llega a la regla de prescripción.
    dispense($this->auxiliar, $body)->assertStatus(422)->assertJsonPath('code', 'prescription_exhausted');
});

it('repite sin importar orden de claves, campos ajenos ni credenciales del autorizador', function () {
    $body = dispensationBody($this->prescription, $this->warehouse, [[$this->product, 2]]);
    $first = dispense($this->auxiliar, $body, $this->key)->assertCreated();

    $reordered = array_reverse([...$body, 'dispensed_by' => 999, 'authorizer_email' => 'x@dispensart.test', 'authorizer_password' => 'otra'], true);

    dispense($this->auxiliar, $reordered, $this->key)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $first->json('data.id'));
    expect(Dispensation::count())->toBe(1);
});

it('rechaza la misma clave con otra cantidad sin cambios', function () {
    dispense($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 3]]), $this->key)->assertCreated();
    $after = dispensationState();

    dispense($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 4]]), $this->key)
        ->assertStatus(422)
        ->assertExactJson(['code' => 'idempotency_key_reused', 'message' => __('errors.idempotency_key_reused')]);

    expect(dispensationState())->toBe($after);
});

it('rechaza una clave ausente, corta o con espacios sin cambios', function (?string $key) {
    $before = dispensationState();
    $headers = $key === null ? [] : ['Idempotency-Key' => $key];

    $this->actingAs($this->auxiliar)
        ->postJson('/api/dispensations', dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]), $headers)
        ->assertStatus(422)
        ->assertExactJson(['code' => 'invalid_idempotency_key', 'message' => __('errors.invalid_idempotency_key')]);

    expect(dispensationState())->toBe($before);
})->with([
    'sin clave' => [null],
    '10 caracteres' => ['abcdefghij'],
    'con espacios' => ['clave con espacios 123'],
    '129 caracteres' => [str_repeat('a', 129)],
]);

it('no consume la clave con un rechazo: tras reponer la existencia el mismo reintento crea la dispensación', function () {
    $regente = User::factory()->regente()->create();
    $body = dispensationBody($this->prescription, $this->warehouse, [[$this->product, 5]]);
    $this->actingAs($regente)->postJson('/api/stock-adjustments', adjustmentBody($this->stock, -18))->assertCreated();

    dispense($this->auxiliar, $body, $this->key)->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');
    $this->actingAs($regente)->postJson('/api/stock-adjustments', adjustmentBody($this->stock, 10))->assertCreated();

    $retry = dispense($this->auxiliar, $body, $this->key);

    $retry->assertCreated();
    expect($retry->headers->has('Idempotent-Replayed'))->toBeFalse()
        ->and(Dispensation::count())->toBe(1);
});

it('acepta la misma clave de otro usuario como una dispensación nueva y propia', function () {
    $other = User::factory()->regente()->create();
    $first = dispense($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 3]]), $this->key)->assertCreated();
    $own = prescriptionWith([[$this->product, 5]]);

    $second = dispense($other, dispensationBody($own, $this->warehouse, [[$this->product, 2]]), $this->key);

    $second->assertCreated()
        ->assertJsonPath('data.dispensed_by', $other->id)
        ->assertJsonPath('data.prescription_id', $own->id);
    expect($second->headers->has('Idempotent-Replayed'))->toBeFalse()
        ->and($second->json('data.id'))->not->toBe($first->json('data.id'))
        ->and(Dispensation::count())->toBe(2);
});
