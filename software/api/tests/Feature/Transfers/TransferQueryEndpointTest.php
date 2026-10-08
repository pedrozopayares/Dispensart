<?php

use App\Enums\Role;
use App\Enums\TransferStatus;
use App\Models\Lot;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// transfers "Consulta de traslados": GET /api/transfers y GET /api/transfers/{id} por HTTP real.

beforeEach(function () {
    $this->auditor = User::factory()->auditor()->create();
});

it('lista del más reciente al más antiguo con meta para cada rol con lectura', function (Role $role) {
    $old = Transfer::factory()->create(['created_at' => now()->subDays(2)]);
    $new = Transfer::factory()->create(['created_at' => now()->subDay()]);
    $newest = Transfer::factory()->create(['created_at' => now()->subDay()]);

    $this->actingAs(User::factory()->withRole($role)->create())->getJson('/api/transfers')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newest->id, $new->id, $old->id])
        ->assertJsonPath('data.0.status', 'BORRADOR')
        ->assertJsonStructure(['data' => [['id', 'status', 'origin_warehouse', 'destination_warehouse', 'created_by', 'created_at']], 'links', 'meta' => ['current_page', 'per_page', 'total']])
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 3);
})->with(['auxiliar_farmacia' => [Role::AuxiliarFarmacia], 'regente_farmacia' => [Role::RegenteFarmacia], 'auditor' => [Role::Auditor]]);

it('filtra por estado y bodegas combinados con Y', function () {
    $inTransit = Transfer::factory()->inTransit()->create();
    Transfer::factory()->approved()->create(['origin_warehouse_id' => $inTransit->origin_warehouse_id]);
    Transfer::factory()->received()->create();

    $this->actingAs($this->auditor)->getJson('/api/transfers?status=EN_TRANSITO')
        ->assertOk()->assertJsonPath('data.*.id', [$inTransit->id]);
    $this->actingAs($this->auditor)->getJson("/api/transfers?status=EN_TRANSITO&origin_warehouse_id={$inTransit->origin_warehouse_id}&destination_warehouse_id={$inTransit->destination_warehouse_id}")
        ->assertOk()->assertJsonPath('data.*.id', [$inTransit->id]);
    $this->actingAs($this->auditor)->getJson("/api/transfers?status=APROBADO&destination_warehouse_id={$inTransit->destination_warehouse_id}")
        ->assertOk()->assertJsonPath('data', []);
});

it('devuelve el detalle con líneas, discrepancias y el actor y la fecha de cada transición', function () {
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 3], [Lot::factory()->create(), 2]], TransferStatus::PartiallyReceived, ['notes' => 'Urgente']);
    [$first, $second] = $transfer->lines->all();
    $first->forceFill(['received_quantity' => 2])->save();
    $second->forceFill(['received_quantity' => 2])->save();
    (new TransferDiscrepancy)->forceFill(['transfer_id' => $transfer->id, 'transfer_line_id' => $first->id, 'shortage' => 1, 'status' => 'pending'])->save();
    $transfer->refresh();

    $response = $this->actingAs(User::factory()->auxiliar()->create())->getJson("/api/transfers/{$transfer->id}");

    $response->assertOk()
        ->assertJsonPath('data.status', 'RECIBIDO_PARCIAL')
        ->assertJsonPath('data.notes', 'Urgente')
        ->assertJsonPath('data.lines.0.product.id', $lot->product_id)
        ->assertJsonPath('data.lines.0.lot.lot_code', $lot->lot_code)
        ->assertJsonPath('data.lines.0.quantity', 3)
        ->assertJsonPath('data.lines.0.received_quantity', 2)
        ->assertJsonPath('data.discrepancies.0.line_id', $first->id)
        ->assertJsonPath('data.discrepancies.0.lot_id', $lot->id)
        ->assertJsonPath('data.discrepancies.0.shortage', 1)
        ->assertJsonPath('data.discrepancies.0.status', 'pending');
    foreach (['created', 'requested', 'approved', 'dispatched', 'received'] as $step) {
        $actor = $step === 'created' ? $transfer->created_by : $transfer->{$step.'_by'};
        $response->assertJsonPath("data.{$step}_by.id", $actor);
        expect($response->json("data.{$step}_at"))->toBeString()->toEndWith('+00:00');
    }
});

it('devuelve data vacío sin resultados', function (string $query) {
    Transfer::factory()->create();

    $this->actingAs($this->auditor)->getJson("/api/transfers{$query}")->assertOk()->assertJsonPath('data', []);
})->with(['sin anulados' => ['?status=ANULADO'], 'página 999' => ['?page=999']]);

it('rechaza filtros mal formados en el parámetro afectado', function (string $query, string $field) {
    $this->actingAs($this->auditor)->getJson("/api/transfers{$query}")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($field);
})->with([
    'estado desconocido' => ['?status=PERDIDO', 'status'],
    'bodega no numérica' => ['?origin_warehouse_id=abc', 'origin_warehouse_id'],
    'per_page 101' => ['?per_page=101', 'per_page'],
]);

it('responde 404 a un traslado inexistente', function () {
    $this->actingAs($this->auditor)->getJson('/api/transfers/999999')->assertNotFound()->assertJsonPath('code', 'not_found');
});

it('responde 404 sin 500 a ids de traslado o discrepancia fuera de rango o no numéricos', function (string $method, string $uri) {
    $this->actingAs(User::factory()->regente()->create())->json($method, $uri)
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
})->with([
    'detalle con 19 dígitos' => ['GET', '/api/transfers/9999999999999999999'],
    'despacho con 19 dígitos' => ['POST', '/api/transfers/9999999999999999999/dispatch'],
    'detalle no numérico' => ['GET', '/api/transfers/abc'],
    'discrepancia con 19 dígitos' => ['POST', '/api/transfers/1/discrepancies/9999999999999999999/resolve'],
]);

it('rechaza con 403 la lista y el detalle a medico y admin', function (Role $role) {
    $transfer = Transfer::factory()->create();
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->getJson('/api/transfers')->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->actingAs($user)->getJson("/api/transfers/{$transfer->id}")->assertForbidden()->assertJsonPath('code', 'forbidden');
})->with(['medico' => [Role::Medico], 'admin' => [Role::Admin]]);

it('responde 401 sin sesión a la lista y al detalle', function () {
    $transfer = Transfer::factory()->create();

    $this->getJson('/api/transfers')->assertUnauthorized();
    $this->getJson("/api/transfers/{$transfer->id}")->assertUnauthorized();
});
