<?php

use App\Enums\TransferStatus;
use App\Models\AuditEvent;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// transfers "Resolución de discrepancias" (salvo la carrera), Bitácora («Anulación y resolución registradas» en la
// resolución) y Movimientos («Pasos sin movimiento de stock»):
// POST /api/transfers/{id}/discrepancies/{discrepancyId}/resolve por HTTP real.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
    $this->lot = Lot::factory()->create();
});

/**
 * Traslado RECIBIDO_PARCIAL de verdad (despachado y recibido por la API) con una discrepancia pendiente de
 * faltante `$shortage` sobre el lote dado.
 *
 * @return array{0: Transfer, 1: TransferDiscrepancy}
 */
function partialTransfer(Lot $lot, int $shortage = 1): array
{
    $transfer = transferWith([[$lot, 3]], TransferStatus::Approved);
    originStock($transfer, $lot, 3);
    $auxiliar = User::factory()->auxiliar()->create();
    transferAction($auxiliar, $transfer, 'dispatch')->assertOk();
    transferAction($auxiliar, $transfer, 'receive', receiveBody($transfer, [3 - $shortage]))->assertOk();

    return [$transfer, TransferDiscrepancy::where('transfer_id', $transfer->id)->sole()];
}

function resolveUrl(Transfer $transfer, TransferDiscrepancy|int $discrepancy): string
{
    $id = $discrepancy instanceof TransferDiscrepancy ? $discrepancy->id : $discrepancy;

    return "/api/transfers/{$transfer->id}/discrepancies/{$id}/resolve";
}

it('devuelve al origen: discrepancia resuelta, ajuste de +1 con el motivo y traslado sigue RECIBIDO_PARCIAL', function () {
    [$transfer, $discrepancy] = partialTransfer($this->lot);

    $this->actingAs($this->regente)->postJson(resolveUrl($transfer, $discrepancy), [
        'resolution' => 'returned_to_origin', 'reason' => 'Unidad no cargada en el despacho',
    ])->assertOk()
        ->assertJsonPath('data.id', $discrepancy->id)
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution', 'returned_to_origin')
        ->assertJsonPath('data.resolution_reason', 'Unidad no cargada en el despacho')
        ->assertJsonPath('data.resolved_by.id', $this->regente->id)
        ->assertJsonPath('data.lot_id', $this->lot->id);

    $adjustment = KardexMovement::query()->where('type', 'ajuste')->sole();
    expect(stockAt($transfer->origin_warehouse_id, $this->lot)?->quantity)->toBe(1)
        ->and([$adjustment->quantity, $adjustment->warehouse_id, $adjustment->user_id])->toBe([1, $transfer->origin_warehouse_id, $this->regente->id])
        ->and($adjustment->reason)->toContain('Unidad no cargada en el despacho')->toContain((string) $transfer->id)
        ->and($transfer->fresh()?->status)->toBe(TransferStatus::PartiallyReceived);
    $row = AuditEvent::sole();
    expect([$row->action->value, $row->actor_id, $row->subject_type, $row->subject_id, $row->details])
        ->toBe(['transfer.discrepancy_resolved', $this->regente->id, 'transfer', $transfer->id, ['discrepancy_id' => $discrepancy->id]]);
});

it('declara pérdida sin mover ninguna existencia ni movimiento', function () {
    [$transfer, $discrepancy] = partialTransfer($this->lot);
    $before = transferState();

    $this->actingAs($this->regente)->postJson(resolveUrl($transfer, $discrepancy), ['resolution' => 'written_off', 'reason' => 'Rotura en transporte'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution', 'written_off');

    $after = transferState();
    expect([$after['stocks'], $after['kardex']])->toBe([$before['stocks'], $before['kardex']]);
});

it('rechaza la devolución sobre un lote vencido con 422 lot_expired, pendiente y sin movimiento', function () {
    [$transfer, $discrepancy] = partialTransfer($this->lot);
    $this->lot->forceFill(['expires_on' => BusinessCalendar::today()->toDateString()])->save();
    $kardex = KardexMovement::count();

    $this->actingAs($this->regente)->postJson(resolveUrl($transfer, $discrepancy), ['resolution' => 'returned_to_origin', 'reason' => 'Devolver'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'lot_expired');

    expect($discrepancy->fresh()?->status->value)->toBe('pending')
        ->and(KardexMovement::count())->toBe($kardex)
        ->and(AuditEvent::count())->toBe(0);
});

it('rechaza una resolución inválida o incompleta en el campo afectado y sigue pendiente', function (array $body, string $field) {
    [$transfer, $discrepancy] = partialTransfer($this->lot);

    $this->actingAs($this->regente)->postJson(resolveUrl($transfer, $discrepancy), $body)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($field);

    expect($discrepancy->fresh()?->status->value)->toBe('pending');
})->with([
    'sin resolución' => [['reason' => 'x'], 'resolution'],
    'resolución donated' => [['resolution' => 'donated', 'reason' => 'x'], 'resolution'],
    'sin motivo' => [['resolution' => 'written_off'], 'reason'],
    'motivo de espacios' => [['resolution' => 'written_off', 'reason' => '   '], 'reason'],
    'motivo de 501' => [['resolution' => 'written_off', 'reason' => str_repeat('r', 501)], 'reason'],
]);

it('rechaza resolver de nuevo una discrepancia resuelta con 409 sin movimiento nuevo', function () {
    [$transfer, $discrepancy] = partialTransfer($this->lot);
    $this->actingAs($this->regente)->postJson(resolveUrl($transfer, $discrepancy), ['resolution' => 'returned_to_origin', 'reason' => 'Una vez'])->assertOk();
    $kardex = KardexMovement::count();

    $this->actingAs($this->regente)->postJson(resolveUrl($transfer, $discrepancy), ['resolution' => 'returned_to_origin', 'reason' => 'Dos veces'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'discrepancy_already_resolved');

    expect(KardexMovement::count())->toBe($kardex);
});

it('responde 404 a una discrepancia de otro traslado sin cambiar ninguna', function () {
    [, $discrepancy] = partialTransfer($this->lot);
    [$other] = partialTransfer(Lot::factory()->create());

    $this->actingAs($this->regente)->postJson(resolveUrl($other, $discrepancy), ['resolution' => 'written_off', 'reason' => 'x'])
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    expect(TransferDiscrepancy::where('status', 'resolved')->count())->toBe(0);
});

it('rechaza con 403 al auxiliar y la discrepancia sigue pendiente', function () {
    [$transfer, $discrepancy] = partialTransfer($this->lot);

    $this->actingAs($this->auxiliar)->postJson(resolveUrl($transfer, $discrepancy), ['resolution' => 'written_off', 'reason' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    expect($discrepancy->fresh()?->status->value)->toBe('pending');
});

it('no mueve stock al crear, solicitar, aprobar, anular ni al resolver con written_off', function () {
    [$partial, $discrepancy] = partialTransfer($this->lot);
    $before = transferState();
    $lot = Lot::factory()->create();
    $origin = $partial->origin_warehouse_id;

    $id = $this->actingAs($this->auxiliar)->postJson('/api/transfers', [
        'origin_warehouse_id' => $origin, 'destination_warehouse_id' => $partial->destination_warehouse_id,
        'lines' => [['lot_id' => $lot->id, 'quantity' => 1]],
    ])->assertCreated()->json('data.id');
    $transfer = Transfer::findOrFail($id);
    transferAction($this->auxiliar, $transfer, 'request')->assertOk();
    transferAction($this->regente, $transfer, 'approve')->assertOk();
    transferAction($this->regente, $transfer, 'void', ['reason' => 'Sin uso'])->assertOk();
    $this->actingAs($this->regente)->postJson(resolveUrl($partial, $discrepancy), ['resolution' => 'written_off', 'reason' => 'Perdida'])->assertOk();

    $after = transferState();
    expect([$after['stocks'], $after['kardex']])->toBe([$before['stocks'], $before['kardex']]);
});
