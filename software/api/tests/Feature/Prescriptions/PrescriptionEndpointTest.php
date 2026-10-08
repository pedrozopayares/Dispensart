<?php

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// prescriptions "Creación de prescripciones por el médico" y audit-trail "Prescripción registrada":
// POST /api/prescriptions por HTTP real.

beforeEach(function () {
    $this->medico = User::factory()->medico()->create();
    $this->patient = Patient::factory()->create();
    $this->products = Product::factory()->count(2)->create();
});

/**
 * @return array<string, mixed>
 */
function prescriptionBody(array $overrides = []): array
{
    $test = test();

    return [
        'patient_id' => $test->patient->id,
        'valid_until' => BusinessCalendar::today()->addDays(29)->toDateString(),
        'items' => [
            ['product_id' => $test->products[0]->id, 'quantity' => 10],
            ['product_id' => $test->products[1]->id, 'quantity' => 5],
        ],
        ...$overrides,
    ];
}

it('crea la prescripción vigente a nombre del médico con ítems sin dispensar y su fila en la bitácora', function () {
    $response = $this->actingAs($this->medico)->postJson('/api/prescriptions', prescriptionBody());

    $response->assertCreated()
        ->assertJsonPath('data.patient_id', $this->patient->id)
        ->assertJsonPath('data.status', 'vigente')
        ->assertJsonPath('data.prescriber.id', $this->medico->id)
        ->assertJsonPath('data.items.0.product.id', $this->products[0]->id)
        ->assertJsonPath('data.items.0.prescribed_quantity', 10)
        ->assertJsonPath('data.items.0.dispensed_quantity', 0)
        ->assertJsonPath('data.items.0.pending_quantity', 10)
        ->assertJsonPath('data.items.1.pending_quantity', 5);

    $id = $response->json('data.id');
    $event = AuditEvent::sole();
    expect(Prescription::findOrFail($id)->prescriber_id)->toBe($this->medico->id)
        ->and($event->action->value)->toBe('prescription.created')
        ->and($event->actor_id)->toBe($this->medico->id)
        ->and($event->subject_type)->toBe('prescription')
        ->and($event->subject_id)->toBe($id)
        ->and($event->correlation_id)->toBe($response->headers->get('X-Correlation-Id'));
});

it('ignora el médico enviado por el cliente', function () {
    $other = User::factory()->medico()->create();

    $response = $this->actingAs($this->medico)->postJson('/api/prescriptions', prescriptionBody(['prescriber_id' => $other->id]));

    $response->assertCreated()->assertJsonPath('data.prescriber.id', $this->medico->id);
    expect(Prescription::sole()->prescriber_id)->toBe($this->medico->id);
});

it('rechaza datos inválidos o incompletos por campo sin crear prescripción', function (Closure $body, string $field) {
    $this->actingAs($this->medico)->postJson('/api/prescriptions', $body())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field], responseKey: 'errors');

    expect(Prescription::count())->toBe(0)->and(AuditEvent::count())->toBe(0);
})->with([
    'sin items' => [fn () => array_diff_key(prescriptionBody(), ['items' => true]), 'items'],
    'items vacío' => [fn () => prescriptionBody(['items' => []]), 'items'],
    '21 ítems' => [fn () => prescriptionBody(['items' => Product::factory()->count(21)->create()->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 1])->all()]), 'items'],
    'cantidad 0' => [fn () => prescriptionBody(['items' => [['product_id' => test()->products[0]->id, 'quantity' => 0]]]), 'items.0.quantity'],
    'producto repetido' => [fn () => prescriptionBody(['items' => [['product_id' => test()->products[0]->id, 'quantity' => 1], ['product_id' => test()->products[0]->id, 'quantity' => 2]]]), 'items.1.product_id'],
    'producto inexistente' => [fn () => prescriptionBody(['items' => [['product_id' => 999_999, 'quantity' => 1]]]), 'items.0.product_id'],
    'paciente inexistente' => [fn () => prescriptionBody(['patient_id' => 999_999]), 'patient_id'],
    'sin valid_until' => [fn () => array_diff_key(prescriptionBody(), ['valid_until' => true]), 'valid_until'],
]);

it('rechaza una vigencia de ayer en Bogotá', function () {
    $this->actingAs($this->medico)
        ->postJson('/api/prescriptions', prescriptionBody(['valid_until' => BusinessCalendar::today()->subDay()->toDateString()]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['valid_until'], responseKey: 'errors');

    // Control positivo: hoy sí se admite.
    $this->actingAs($this->medico)
        ->postJson('/api/prescriptions', prescriptionBody(['valid_until' => BusinessCalendar::today()->toDateString()]))
        ->assertCreated();
    expect(Prescription::count())->toBe(1);
});

it('rechaza con 403 a los demás roles sin crear prescripción', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create())->postJson('/api/prescriptions', prescriptionBody())
        ->assertForbidden()
        ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);

    expect(Prescription::count())->toBe(0);
})->with([
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia],
    'regente_farmacia' => [Role::RegenteFarmacia],
    'auditor' => [Role::Auditor],
    'admin' => [Role::Admin],
]);

it('responde 401 sin sesión sin crear prescripción', function () {
    $this->postJson('/api/prescriptions', prescriptionBody())->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');

    expect(Prescription::count())->toBe(0);
});

it('rechaza con 419 una prescripción desde la SPA sin X-XSRF-TOKEN y la acepta con él', function () {
    $spa = new SpaClient($this);
    $spa->loginAs($this->medico);

    $spa->post('/api/prescriptions', prescriptionBody(), withXsrf: false)
        ->assertStatus(419)
        ->assertJsonPath('code', 'csrf_token_mismatch');
    expect(Prescription::count())->toBe(0);

    $spa->post('/api/prescriptions', prescriptionBody())->assertCreated();
    expect(Prescription::count())->toBe(1);
});
