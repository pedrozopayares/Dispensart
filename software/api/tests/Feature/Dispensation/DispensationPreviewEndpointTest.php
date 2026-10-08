<?php

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// dispensation "Vista previa de asignación FEFO": POST /api/dispensations/preview por HTTP real. La vista
// previa no escribe ni bloquea: la foto de dispensationState() no cambia.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
    $this->l1 = lotStock($this->warehouse, $this->product, 10, 3);
    $this->l2 = lotStock($this->warehouse, $this->product, 40, 10);
    $this->prescription = prescriptionWith([[$this->product, 30]]);
});

function preview(User $user, array $body): TestResponse
{
    return test()->actingAs($user)->postJson('/api/dispensations/preview', $body);
}

it('devuelve las asignaciones FEFO en orden sin cambiar existencias, kardex ni saldos', function () {
    $before = dispensationState();

    $response = preview($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 5]]));

    $response->assertOk()
        ->assertJsonPath('data.prescription_id', $this->prescription->id)
        ->assertJsonPath('data.warehouse_id', $this->warehouse->id)
        ->assertJsonPath('data.fulfillable', true)
        ->assertJsonPath('data.requires_authorization', false)
        ->assertJsonPath('data.items.0.requested', 5)
        ->assertJsonPath('data.items.0.shortage', 0)
        ->assertJsonPath('data.items.0.expired_excluded_quantity', 0);
    expect(array_map(fn ($a) => [$a['lot_id'], $a['quantity']], $response->json('data.items.0.allocations')))
        ->toBe([[$this->l1->lot_id, 3], [$this->l2->lot_id, 2]])
        ->and(dispensationState())->toBe($before);
});

it('muestra el faltante sin error', function () {
    $response = preview($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 20]]));

    $response->assertOk()
        ->assertJsonPath('data.fulfillable', false)
        ->assertJsonPath('data.items.0.available', 13)
        ->assertJsonPath('data.items.0.shortage', 7)
        ->assertJsonCount(2, 'data.items.0.allocations');
});

it('excluye y cuenta el lote vencido', function () {
    $expired = lotStock($this->warehouse, $this->product, -1, 5);

    $response = preview($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 5]]));

    $response->assertOk()->assertJsonPath('data.items.0.expired_excluded_quantity', 5);
    expect(array_column($response->json('data.items.0.allocations'), 'lot_id'))->not->toContain($expired->lot_id);
});

it('señala el ítem de control especial en el ítem y en la respuesta', function () {
    $controlled = Product::factory()->controlled()->create();
    lotStock($this->warehouse, $controlled, 30, 5);
    $prescription = prescriptionWith([[$this->product, 5], [$controlled, 2]]);

    $response = preview($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 1], [$controlled, 1]]));

    $response->assertOk()
        ->assertJsonPath('data.requires_authorization', true)
        ->assertJsonPath('data.items.0.requires_authorization', false)
        ->assertJsonPath('data.items.1.requires_authorization', true);
});

it('rechaza la vista previa de una prescripción vencida o agotada', function (Closure $prescription, string $code) {
    $p = $prescription($this->product);

    preview($this->auxiliar, dispensationBody($p, $this->warehouse, [[$this->product, 1]]))
        ->assertStatus(422)
        ->assertExactJson(['code' => $code, 'message' => __('errors.'.$code)]);
})->with([
    'vencida' => [fn (Product $p) => prescriptionWith([[$p, 10]], ['valid_until' => now()->subDays(2)->toDateString()]), 'prescription_expired'],
    'agotada' => [fn (Product $p) => prescriptionWith([[$p, 10, 10]]), 'prescription_exhausted'],
]);

it('rechaza datos inválidos por campo', function (Closure $mutate, string $field) {
    $body = dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]);

    preview($this->auxiliar, $mutate($body))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field], responseKey: 'errors');
})->with([
    'sin warehouse_id' => [fn (array $b) => array_diff_key($b, ['warehouse_id' => true]), 'warehouse_id'],
    'cantidad 0' => [fn (array $b) => [...$b, 'items' => [[...$b['items'][0], 'quantity' => 0]]], 'items.0.quantity'],
    'ítem de otra prescripción' => [fn (array $b) => [...$b, 'items' => [['prescription_item_id' => prescriptionWith([[test()->product, 3]])->items->sole()->id, 'quantity' => 1]]], 'items.0.prescription_item_id'],
    'prescripción inexistente' => [fn (array $b) => [...$b, 'prescription_id' => 999_999], 'prescription_id'],
    'bodega inexistente' => [fn (array $b) => [...$b, 'warehouse_id' => 999_999], 'warehouse_id'],
]);

it('rechaza con 403 a los roles sin permiso de dispensar', function (Role $role) {
    preview(User::factory()->withRole($role)->create(), dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]))
        ->assertForbidden()
        ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);
})->with([
    'medico' => [Role::Medico],
    'auditor' => [Role::Auditor],
    'admin' => [Role::Admin],
]);

it('responde 401 sin sesión', function () {
    $this->postJson('/api/dispensations/preview', dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('rechaza con 419 una vista previa desde la SPA sin X-XSRF-TOKEN y la acepta con él', function () {
    $spa = new SpaClient($this);
    $spa->loginAs($this->auxiliar);
    $body = dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]);

    $spa->post('/api/dispensations/preview', $body, withXsrf: false)->assertStatus(419);
    $spa->post('/api/dispensations/preview', $body)->assertOk();
});
