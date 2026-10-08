<?php

use App\Enums\TransferStatus;
use App\Models\AuditEvent;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// transfers "Solicitud del traslado", "Aprobación con segregación de funciones" y Bitácora («Aprobación
// registrada», «Rechazo sin fila»): POST /api/transfers/{id}/request y /approve por HTTP real.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
    $this->lot = Lot::factory()->create();
});

describe('solicitud', function () {
    it('pasa el borrador a SOLICITADO con el creador como solicitante y la fecha del servidor', function () {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft, ['created_by' => $this->auxiliar->id]);
        $this->freezeSecond();

        transferAction($this->auxiliar, $transfer, 'request')
            ->assertOk()
            ->assertJsonPath('data.status', 'SOLICITADO')
            ->assertJsonPath('data.requested_by.id', $this->auxiliar->id)
            ->assertJsonPath('data.requested_at', now()->toIso8601String());
    });

    it('rechaza con 403 la solicitud de un borrador ajeno y sigue BORRADOR sin solicitante', function (string $who) {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft, ['created_by' => $this->auxiliar->id]);
        $other = $who === 'auxiliar' ? User::factory()->auxiliar()->create() : $this->regente;

        transferAction($other, $transfer, 'request')->assertForbidden()->assertJsonPath('code', 'forbidden');

        expect($transfer->fresh()?->status)->toBe(TransferStatus::Draft)->and($transfer->fresh()?->requested_by)->toBeNull();
    })->with(['otro auxiliar' => ['auxiliar'], 'regente no creador' => ['regente']]);

    it('rechaza con 403 la solicitud del auditor', function () {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft);

        transferAction(User::factory()->auditor()->create(), $transfer, 'request')->assertForbidden()->assertJsonPath('code', 'forbidden');
    });
});

describe('aprobación', function () {
    it('aprueba la solicitud de un auxiliar con el regente como aprobador, sin stock y con una fila de bitácora', function () {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Requested, ['created_by' => $this->auxiliar->id]);
        $stock = originStock($transfer, $this->lot, 10);
        $before = transferState();

        transferAction($this->regente, $transfer, 'approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'APROBADO')
            ->assertJsonPath('data.approved_by.id', $this->regente->id);

        expect($stock->fresh()?->quantity)->toBe(10)
            ->and(transferState()['kardex'])->toBe($before['kardex'])
            ->and(AuditEvent::count())->toBe(1);
        $row = AuditEvent::sole();
        expect([$row->action->value, $row->actor_id, $row->subject_type, $row->subject_id, $row->details])
            ->toBe(['transfer.approved', $this->regente->id, 'transfer', $transfer->id, []])
            ->and($row->correlation_id)->not->toBeNull();
    });

    it('aprueba la solicitud de otro regente', function () {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Requested, ['created_by' => User::factory()->regente()->create()->id]);

        transferAction($this->regente, $transfer, 'approve')->assertOk()->assertJsonPath('data.status', 'APROBADO');
    });

    it('rechaza con 403 segregation_of_duties al regente que creó y solicitó, sin aprobador ni fila', function () {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Requested, ['created_by' => $this->regente->id]);

        transferAction($this->regente, $transfer, 'approve')
            ->assertForbidden()
            ->assertJsonPath('code', 'segregation_of_duties')
            ->assertJsonPath('message', __('errors.segregation_of_duties'));

        expect($transfer->fresh()?->status)->toBe(TransferStatus::Requested)
            ->and($transfer->fresh()?->approved_by)->toBeNull()
            ->and(AuditEvent::count())->toBe(0);
    });

    it('evalúa la segregación antes que el estado: el creador regente aprueba su BORRADOR y recibe 403, no 409', function () {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Draft, ['created_by' => $this->regente->id]);

        transferAction($this->regente, $transfer, 'approve')->assertForbidden()->assertJsonPath('code', 'segregation_of_duties');
    });

    it('rechaza con 403 forbidden al auxiliar que no es el solicitante y sigue SOLICITADO', function () {
        $transfer = transferWith([[$this->lot, 3]], TransferStatus::Requested);

        transferAction($this->auxiliar, $transfer, 'approve')->assertForbidden()->assertJsonPath('code', 'forbidden');

        expect($transfer->fresh()?->status)->toBe(TransferStatus::Requested);
    });
});
