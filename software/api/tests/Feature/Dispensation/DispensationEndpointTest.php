<?php

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\Dispensation;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// dispensation "Asignación FEFO en la dispensación", "Dispensación contra prescripción vigente", "Acceso a la
// dispensación", "Movimiento de kardex por lote consumido"; prescriptions "Estado no vigente rechaza la
// dispensación" y "Parciales acumuladas"; audit-trail "Dispensación ordinaria registrada" y "Rechazo sin fila
// de operación": POST /api/dispensations por HTTP real, secuencial.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
});

/**
 * @return array<int, int> lote → cantidad tomada, en el orden de las líneas
 */
function linesOf(TestResponse $response): array
{
    $lines = [];
    foreach ($response->json('data.lines') as $line) {
        $lines[] = [$line['lot_id'], $line['quantity']];
    }

    return $lines;
}

describe('FEFO', function () {
    it('consume L1 y luego L2 y deja L3 intacto', function () {
        $l1 = lotStock($this->warehouse, $this->product, 10, 3);
        $l2 = lotStock($this->warehouse, $this->product, 40, 10);
        $l3 = lotStock($this->warehouse, $this->product, 90, 10);
        $prescription = prescriptionWith([[$this->product, 10]]);

        $response = dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 5]]));

        $response->assertCreated();
        expect(linesOf($response))->toBe([[$l1->lot_id, 3], [$l2->lot_id, 2]])
            ->and([$l1->fresh()?->quantity, $l2->fresh()?->quantity, $l3->fresh()?->quantity])->toBe([0, 8, 10]);
    });

    it('desempata por id de lote a igual vencimiento', function () {
        $a = lotStock($this->warehouse, $this->product, 30, 3);
        $b = lotStock($this->warehouse, $this->product, 30, 3);
        $prescription = prescriptionWith([[$this->product, 10]]);

        $response = dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 4]]));

        $response->assertCreated();
        expect(linesOf($response))->toBe([[min($a->lot_id, $b->lot_id), 3], [max($a->lot_id, $b->lot_id), 1]]);
    });

    it('nunca selecciona un lote vencido ni le escribe movimiento', function () {
        $l0 = lotStock($this->warehouse, $this->product, -1, 50);
        $l1 = lotStock($this->warehouse, $this->product, 10, 2);
        $prescription = prescriptionWith([[$this->product, 10]]);

        $response = dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 2]]));

        $response->assertCreated();
        expect(linesOf($response))->toBe([[$l1->lot_id, 2]])
            ->and($l0->fresh()?->quantity)->toBe(50)
            ->and(movementsOf($l0))->toHaveCount(1);
    });

    it('excluye el lote que vence hoy en Bogotá y responde 409 sin cambios', function () {
        $this->travelTo(CarbonImmutable::parse('2027-03-14 23:30:00', 'America/Bogota'));
        $lot = Lot::factory()->create(['product_id' => $this->product->id, 'expires_on' => '2027-03-14']);
        stockOf(5, ['warehouse_id' => $this->warehouse->id, 'lot_id' => $lot->id]);
        $prescription = prescriptionWith([[$this->product, 10]]);
        $before = dispensationState();

        dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 1]]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'insufficient_stock');

        expect(dispensationState())->toBe($before);
    });

    it('no toca otra bodega aunque tenga el lote que vence primero', function () {
        $central = Warehouse::factory()->create();
        $first = lotStock($central, $this->product, 5, 10);
        $own = lotStock($this->warehouse, $this->product, 60, 10);
        $prescription = prescriptionWith([[$this->product, 10]]);

        $response = dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 3]]));

        $response->assertCreated();
        expect(linesOf($response))->toBe([[$own->lot_id, 3]])
            ->and($first->fresh()?->quantity)->toBe(10);
    });

    it('rechaza todo con 409 y los faltantes si un ítem no alcanza', function () {
        $other = Product::factory()->create();
        lotStock($this->warehouse, $this->product, 30, 10);
        lotStock($this->warehouse, $other, 30, 5);
        lotStock($this->warehouse, $other, -3, 20); // vencido: no cuenta como disponible
        $prescription = prescriptionWith([[$this->product, 10], [$other, 10]]);
        $before = dispensationState();

        $response = dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 2], [$other, 8]]));

        $response->assertStatus(409)->assertExactJson([
            'code' => 'insufficient_stock',
            'message' => __('errors.insufficient_stock'),
            'shortages' => [[
                'prescription_item_id' => itemOf($prescription, $other)->id, 'product_id' => $other->id,
                'requested' => 8, 'available' => 5,
            ]],
        ]);
        expect(dispensationState())->toBe($before);
    });
});

describe('prescripción', function () {
    beforeEach(function () {
        $this->stock = lotStock($this->warehouse, $this->product, 30, 100);
        $this->prescription = prescriptionWith([[$this->product, 10]]);
    });

    it('dispensa un parcial, lo acumula, agota la prescripción y rechaza una unidad más', function () {
        $first = dispense($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 4]]));

        $first->assertCreated()
            ->assertJsonPath('data.prescription_id', $this->prescription->id)
            ->assertJsonPath('data.patient_id', $this->prescription->patient_id)
            ->assertJsonPath('data.warehouse_id', $this->warehouse->id)
            ->assertJsonPath('data.dispensed_by', $this->auxiliar->id)
            ->assertJsonPath('data.authorized_by', null);
        expect(array_keys($first->json('data')))
            ->toBe(['id', 'prescription_id', 'patient_id', 'warehouse_id', 'dispensed_by', 'authorized_by', 'created_at', 'lines'])
            ->and(itemOf($this->prescription, $this->product)->pendingQuantity())->toBe(6);

        dispense($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 6]]))->assertCreated();
        expect(itemOf($this->prescription, $this->product)->dispensed_quantity)->toBe(10)
            ->and($this->prescription->fresh('items')?->statusOn(BusinessCalendar::today())->value)->toBe('agotada');

        $before = dispensationState();
        dispense($this->auxiliar, dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]))
            ->assertStatus(422)
            ->assertExactJson(['code' => 'prescription_exhausted', 'message' => __('errors.prescription_exhausted')]);
        expect(dispensationState())->toBe($before);
    });

    it('rechaza una prescripción vencida ayer sin cambiar saldos', function () {
        $expired = prescriptionWith([[$this->product, 10]], ['valid_until' => BusinessCalendar::today()->subDay()->toDateString()]);
        $before = dispensationState();

        dispense($this->auxiliar, dispensationBody($expired, $this->warehouse, [[$this->product, 1]]))
            ->assertStatus(422)
            ->assertExactJson(['code' => 'prescription_expired', 'message' => __('errors.prescription_expired')]);

        expect(dispensationState())->toBe($before);
    });

    it('rechaza más de lo pendiente sin cambios', function () {
        $prescription = prescriptionWith([[$this->product, 10, 4]]);
        $before = dispensationState();

        dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 7]]))
            ->assertStatus(422)
            ->assertExactJson(['code' => 'exceeds_prescription', 'message' => __('errors.exceeds_prescription')]);

        expect(dispensationState())->toBe($before);
    });

    it('rechaza una solicitud mal formada por campo sin cambios', function (Closure $mutate, string $field) {
        $body = $mutate(dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]));
        $before = dispensationState();

        dispense($this->auxiliar, $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([$field], responseKey: 'errors');

        expect(dispensationState())->toBe($before);
    })->with([
        'sin items' => [fn (array $b) => array_diff_key($b, ['items' => true]), 'items'],
        'items vacío' => [fn (array $b) => [...$b, 'items' => []], 'items'],
        'cantidad 0' => [fn (array $b) => [...$b, 'items' => [[...$b['items'][0], 'quantity' => 0]]], 'items.0.quantity'],
        'cantidad no entera' => [fn (array $b) => [...$b, 'items' => [[...$b['items'][0], 'quantity' => 2.5]]], 'items.0.quantity'],
        'ítem repetido' => [fn (array $b) => [...$b, 'items' => [$b['items'][0], $b['items'][0]]], 'items.1.prescription_item_id'],
        'ítem de otra prescripción' => [fn (array $b) => [...$b, 'items' => [['prescription_item_id' => prescriptionWith([[test()->product, 3]])->items->sole()->id, 'quantity' => 1]]], 'items.0.prescription_item_id'],
        'prescripción inexistente' => [fn (array $b) => [...$b, 'prescription_id' => 999_999], 'prescription_id'],
        'bodega inexistente' => [fn (array $b) => [...$b, 'warehouse_id' => 999_999], 'warehouse_id'],
    ]);
});

describe('kardex y bitácora', function () {
    it('escribe un salida_dispensacion por lote con su saldo, el dispensador y el enlace a su línea', function () {
        $l1 = lotStock($this->warehouse, $this->product, 10, 3);
        $l2 = lotStock($this->warehouse, $this->product, 40, 10);
        $prescription = prescriptionWith([[$this->product, 10]]);
        $kardexBefore = KardexMovement::count();

        $response = dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 5]]))->assertCreated();

        $new = KardexMovement::where('type', 'salida_dispensacion')->orderBy('id')->get();
        expect(KardexMovement::count())->toBe($kardexBefore + 2)
            ->and($new->map(fn ($m) => [$m->lot_id, $m->quantity, $m->balance_after, $m->user_id])->all())->toBe([
                [$l1->lot_id, -3, 0, $this->auxiliar->id],
                [$l2->lot_id, -2, 8, $this->auxiliar->id],
            ])
            ->and(array_column($response->json('data.lines'), 'kardex_movement_id'))->toBe($new->pluck('id')->all());
    });

    it('registra exactamente una fila dispensation.created del dispensador y ninguna de autorización', function () {
        lotStock($this->warehouse, $this->product, 30, 10);
        $prescription = prescriptionWith([[$this->product, 10]]);

        $response = dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$this->product, 1]]))->assertCreated();

        $event = AuditEvent::sole();
        expect($event->action->value)->toBe('dispensation.created')
            ->and($event->actor_id)->toBe($this->auxiliar->id)
            ->and($event->subject_type)->toBe('dispensation')
            ->and($event->subject_id)->toBe($response->json('data.id'))
            // jsonb ordena las claves a su manera: se compara sin orden.
            ->and($event->details)->toEqual(['prescription_id' => $prescription->id, 'warehouse_id' => $this->warehouse->id]);
    });
});

describe('acceso', function () {
    beforeEach(function () {
        lotStock($this->warehouse, $this->product, 30, 10);
        $this->prescription = prescriptionWith([[$this->product, 10]]);
        $this->body = dispensationBody($this->prescription, $this->warehouse, [[$this->product, 1]]);
    });

    it('rechaza con 403 a medico, auditor y admin sin cambios, antes de exigir la clave', function (Role $role) {
        $before = dispensationState();
        $user = User::factory()->withRole($role)->create();

        dispense($user, $this->body)
            ->assertForbidden()
            ->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);
        $this->actingAs($user)->postJson('/api/dispensations', $this->body)->assertForbidden();

        expect(dispensationState())->toBe($before);
    })->with([
        'medico' => [Role::Medico],
        'auditor' => [Role::Auditor],
        'admin' => [Role::Admin],
    ]);

    it('responde 401 sin sesión sin cambios', function () {
        $before = dispensationState();

        $this->postJson('/api/dispensations', $this->body, ['Idempotency-Key' => newIdempotencyKey()])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        expect(dispensationState())->toBe($before);
    });

    it('rechaza con 419 una dispensación desde la SPA sin X-XSRF-TOKEN y la acepta con él', function () {
        $spa = new SpaClient($this);
        $spa->loginAs($this->auxiliar);
        $before = dispensationState();

        $spa->post('/api/dispensations', $this->body, ['Idempotency-Key' => newIdempotencyKey()], withXsrf: false)
            ->assertStatus(419)
            ->assertJsonPath('code', 'csrf_token_mismatch');
        expect(dispensationState())->toBe($before);

        $spa->post('/api/dispensations', $this->body, ['Idempotency-Key' => newIdempotencyKey()])->assertCreated();
        expect(Dispensation::count())->toBe(1);
    });

    it('ignora el dispensador enviado por el cliente', function () {
        $other = User::factory()->auxiliar()->create();

        dispense($this->auxiliar, [...$this->body, 'dispensed_by' => $other->id])
            ->assertCreated()
            ->assertJsonPath('data.dispensed_by', $this->auxiliar->id);
    });

    it('exige la clave antes de validar el cuerpo', function () {
        $this->actingAs($this->auxiliar)->postJson('/api/dispensations', ['items' => []])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_idempotency_key');
    });
});
