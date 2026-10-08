<?php

use App\Enums\Role;
use App\Enums\TransferStatus;
use App\Models\AuditEvent;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// transfers "Anulación del traslado" y Bitácora («Anulación y resolución registradas» en la anulación, «Rechazo
// sin fila», «Sin texto libre en la fila»): POST /api/transfers/{id}/void por HTTP real.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
    $this->lot = Lot::factory()->create();
});

it('deja al creador anular su BORRADOR con anulador, motivo y fecha, sin movimiento', function () {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft, ['created_by' => $this->auxiliar->id]);
    $before = transferState();
    $this->freezeSecond();

    transferAction($this->auxiliar, $transfer, 'void', ['reason' => 'Pedido duplicado'])
        ->assertOk()
        ->assertJsonPath('data.status', 'ANULADO')
        ->assertJsonPath('data.voided_by.id', $this->auxiliar->id)
        ->assertJsonPath('data.void_reason', 'Pedido duplicado')
        ->assertJsonPath('data.voided_at', now()->toIso8601String());

    expect(transferState()['kardex'])->toBe($before['kardex']);
    $row = AuditEvent::sole();
    expect([$row->action->value, $row->actor_id, $row->subject_type, $row->subject_id])
        ->toBe(['transfer.voided', $this->auxiliar->id, 'transfer', $transfer->id]);
});

it('deja al regente anular un APROBADO ajeno sin tocar existencias ni kardex', function () {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::Approved);
    originStock($transfer, $this->lot, 10);
    $before = transferState();

    transferAction($this->regente, $transfer, 'void', ['reason' => 'Ya no se necesita'])->assertOk()->assertJsonPath('data.status', 'ANULADO');

    $after = transferState();
    expect([$after['stocks'], $after['kardex']])->toBe([$before['stocks'], $before['kardex']]);
});

it('deja al creador anular su SOLICITADO', function () {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::Requested, ['created_by' => $this->auxiliar->id]);

    transferAction($this->auxiliar, $transfer, 'void', ['reason' => 'Error de bodega'])->assertOk()->assertJsonPath('data.status', 'ANULADO');
});

it('rechaza con 403 al auxiliar que no es el creador y sigue SOLICITADO', function () {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::Requested);

    transferAction($this->auxiliar, $transfer, 'void', ['reason' => 'Ajeno'])->assertForbidden()->assertJsonPath('code', 'forbidden');

    expect($transfer->fresh()?->status)->toBe(TransferStatus::Requested);
});

it('rechaza sin motivo o con motivo de solo espacios en errors.reason sin cambiar el estado', function (array $body) {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft, ['created_by' => $this->auxiliar->id]);

    transferAction($this->auxiliar, $transfer, 'void', $body)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('reason');

    expect($transfer->fresh()?->status)->toBe(TransferStatus::Draft);
})->with(['sin motivo' => [[]], 'solo espacios' => [['reason' => '   ']], '501 caracteres' => [['reason' => str_repeat('m', 501)]]]);

it('rechaza con 403 a los roles sin anulación', function (Role $role) {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft);

    transferAction(User::factory()->withRole($role)->create(), $transfer, 'void', ['reason' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
})->with(['medico' => [Role::Medico], 'auditor' => [Role::Auditor], 'admin' => [Role::Admin]]);

it('no escribe fila de bitácora al rechazar una anulación con 409', function () {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::InTransit);

    transferAction($this->regente, $transfer, 'void', ['reason' => 'Tarde'])->assertStatus(409)->assertJsonPath('code', 'invalid_transfer_transition');

    expect(AuditEvent::count())->toBe(0);
});

it('no guarda en la fila transfer.voided las observaciones ni el motivo, solo ids', function () {
    $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft, ['created_by' => $this->auxiliar->id, 'notes' => 'NOTA-DISTINTIVA-7781']);

    transferAction($this->auxiliar, $transfer, 'void', ['reason' => 'MOTIVO-DISTINTIVO-4412'])->assertOk();

    $raw = json_encode(AuditEvent::sole()->getAttributes());
    expect($raw)->not->toContain('NOTA-DISTINTIVA-7781')->not->toContain('MOTIVO-DISTINTIVO-4412')
        ->and(AuditEvent::sole()->details)->toBe([]);
});
