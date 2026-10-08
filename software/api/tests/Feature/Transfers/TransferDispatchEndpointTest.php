<?php

use App\Enums\Role;
use App\Enums\TransferStatus;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Transfer;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// transfers "Despacho del traslado", Escrituras protegidas («Sin token CSRF desde la SPA», «Acción sobre traslado
// inexistente») y Movimientos («Despacho rechazado sin movimiento»): POST /api/transfers/{id}/dispatch.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->lotA = Lot::factory()->create();
    $this->lotB = Lot::factory()->create();
});

it('despacha: resta en origen con un salida_traslado por línea y pasa a EN_TRANSITO', function () {
    $transfer = transferWith([[$this->lotA, 3], [$this->lotB, 2]]);
    $a = originStock($transfer, $this->lotA, 10);
    $b = originStock($transfer, $this->lotB, 2);
    $destinationBefore = transferState()['stocks'];

    transferAction($this->auxiliar, $transfer, 'dispatch')
        ->assertOk()
        ->assertJsonPath('data.status', 'EN_TRANSITO')
        ->assertJsonPath('data.dispatched_by.id', $this->auxiliar->id);

    expect([$a->fresh()?->quantity, $b->fresh()?->quantity])->toBe([7, 0]);
    $outbound = KardexMovement::query()->where('type', 'salida_traslado')->orderBy('id')->get();
    expect($outbound->map(fn ($m) => [$m->lot_id, $m->quantity, $m->balance_after, $m->warehouse_id, $m->user_id])->all())
        ->toBe([
            [$this->lotA->id, -3, 7, $transfer->origin_warehouse_id, $this->auxiliar->id],
            [$this->lotB->id, -2, 0, $transfer->origin_warehouse_id, $this->auxiliar->id],
        ])
        ->and(stockAt($transfer->destination_warehouse_id, $this->lotA))->toBeNull()
        ->and(count(transferState()['stocks']))->toBe(count($destinationBefore));
});

it('rechaza con 409 insufficient_stock si una línea no alcanza, sin tocar ninguna existencia', function () {
    // La línea suficiente (A) se crea primero (design D14, M12).
    $transfer = transferWith([[$this->lotA, 3], [$this->lotB, 5]]);
    $a = originStock($transfer, $this->lotA, 10);
    $b = originStock($transfer, $this->lotB, 4);
    $kardex = KardexMovement::count();

    transferAction($this->auxiliar, $transfer, 'dispatch')->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');

    expect($transfer->fresh()?->status)->toBe(TransferStatus::Approved)
        ->and([$a->fresh()?->quantity, $b->fresh()?->quantity])->toBe([10, 4])
        ->and(KardexMovement::count())->toBe($kardex);
});

it('rechaza con 409 insufficient_stock un lote sin existencia en origen', function () {
    $transfer = transferWith([[$this->lotA, 1]]);

    transferAction($this->auxiliar, $transfer, 'dispatch')->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');

    expect($transfer->fresh()?->status)->toBe(TransferStatus::Approved);
});

it('rechaza con 422 lot_expired un lote vencido antes del despacho, aun con existencia suficiente', function () {
    $expired = Lot::factory()->create(['expires_on' => BusinessCalendar::today()->toDateString()]);
    $transfer = transferWith([[$expired, 2]]);
    $stock = originStock($transfer, $expired, 10);
    $kardex = KardexMovement::count();

    transferAction($this->auxiliar, $transfer, 'dispatch')->assertUnprocessable()->assertJsonPath('code', 'lot_expired');

    expect($transfer->fresh()?->status)->toBe(TransferStatus::Approved)
        ->and($stock->fresh()?->quantity)->toBe(10)
        ->and(KardexMovement::count())->toBe($kardex);
});

it('evalúa el vencimiento primero: lote vencido y existencia insuficiente a la vez dan 422 lot_expired', function () {
    $expired = Lot::factory()->create(['expires_on' => BusinessCalendar::today()->subDay()->toDateString()]);
    $transfer = transferWith([[$this->lotA, 5], [$expired, 1]]);
    originStock($transfer, $this->lotA, 1);

    transferAction($this->auxiliar, $transfer, 'dispatch')->assertUnprocessable()->assertJsonPath('code', 'lot_expired');
});

it('rechaza un despacho repetido con 409 invalid_transfer_transition sin nuevas salidas', function () {
    $transfer = transferWith([[$this->lotA, 3]]);
    $stock = originStock($transfer, $this->lotA, 10);
    transferAction($this->auxiliar, $transfer, 'dispatch')->assertOk();

    transferAction($this->auxiliar, $transfer, 'dispatch')->assertStatus(409)->assertJsonPath('code', 'invalid_transfer_transition');

    expect($stock->fresh()?->quantity)->toBe(7)->and(movementsOfType('salida_traslado'))->toBe(1);
});

it('deja salidas y existencias iguales tras un 409 insufficient_stock y luego un 422 lot_expired', function () {
    $short = transferWith([[$this->lotA, 5]]);
    $a = originStock($short, $this->lotA, 4);
    $expired = Lot::factory()->create(['expires_on' => BusinessCalendar::today()->toDateString()]);
    $stale = transferWith([[$expired, 1]]);
    $e = originStock($stale, $expired, 3);
    $outbound = movementsOfType('salida_traslado');

    transferAction($this->auxiliar, $short, 'dispatch')->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');
    transferAction($this->auxiliar, $stale, 'dispatch')->assertUnprocessable()->assertJsonPath('code', 'lot_expired');

    expect(movementsOfType('salida_traslado'))->toBe($outbound)
        ->and([$a->fresh()?->quantity, $e->fresh()?->quantity])->toBe([4, 3]);
});

it('rechaza con 403 a los roles sin despacho y sigue APROBADO', function (Role $role) {
    $transfer = transferWith([[$this->lotA, 1]]);
    originStock($transfer, $this->lotA, 5);

    transferAction(User::factory()->withRole($role)->create(), $transfer, 'dispatch')->assertForbidden()->assertJsonPath('code', 'forbidden');

    expect($transfer->fresh()?->status)->toBe(TransferStatus::Approved);
})->with(['medico' => [Role::Medico], 'auditor' => [Role::Auditor], 'admin' => [Role::Admin]]);

it('rechaza con 419 un despacho desde la SPA sin X-XSRF-TOKEN, sin movimiento, y lo acepta con él', function () {
    $transfer = transferWith([[$this->lotA, 1]]);
    originStock($transfer, $this->lotA, 5);
    $spa = new SpaClient($this);
    $spa->loginAs($this->auxiliar);
    $before = transferState();

    $spa->post("/api/transfers/{$transfer->id}/dispatch", withXsrf: false)->assertStatus(419)->assertJsonPath('code', 'csrf_token_mismatch');
    expect(transferState())->toBe($before);

    $spa->post("/api/transfers/{$transfer->id}/dispatch")->assertOk()->assertJsonPath('data.status', 'EN_TRANSITO');
});

it('responde 404 a una acción sobre un traslado inexistente', function () {
    transferAction(User::factory()->regente()->create(), (new Transfer)->forceFill(['id' => 999999]), 'dispatch')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});
