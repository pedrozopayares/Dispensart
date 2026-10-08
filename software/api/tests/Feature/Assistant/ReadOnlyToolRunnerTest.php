<?php

use App\Enums\Role;
use App\Models\StockMinimum;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Assistant\ToolCallStatus;
use App\Services\Assistant\Tools\GetStockTool;
use App\Services\Assistant\Tools\GetTransferStatusTool;
use App\Services\Assistant\Tools\ReadOnlyToolRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\WritingTool;

uses(RefreshDatabase::class);

// inventory-assistant «Catálogo cerrado…»: «Escritura dentro de una herramienta» (design D5) y «Herramientas con el
// rol del usuario» a nivel del ejecutor (design D4).

test('Escritura dentro de una herramienta: la base la rechaza y la llamada queda failed', function (string $operation) {
    $warehouse = Warehouse::factory()->create(['name' => 'Bodega intacta']);
    StockMinimum::factory()->create(['warehouse_id' => $warehouse->id]);
    $before = [Warehouse::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray(), StockMinimum::count()];

    $record = app(ReadOnlyToolRunner::class)->run(User::factory()->regente()->create(), new WritingTool($operation), []);

    expect($record->status)->toBe(ToolCallStatus::Failed)
        ->and($record->data)->toBeNull()
        ->and([Warehouse::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray(), StockMinimum::count()])->toBe($before);
})->with(['insert', 'update', 'delete']);

test('el modo de solo lectura no se filtra a la transacción exterior', function () {
    app(ReadOnlyToolRunner::class)->run(User::factory()->regente()->create(), new WritingTool, []);

    // La transacción de RefreshDatabase sigue escribible tras la llamada.
    $warehouse = Warehouse::factory()->create();

    expect(Warehouse::query()->whereKey($warehouse->id)->exists())->toBeTrue()
        ->and(DB::selectOne('SHOW transaction_read_only')->transaction_read_only)->toBe('off');
});

test('una herramienta de lectura autorizada corre y devuelve ok', function () {
    $record = app(ReadOnlyToolRunner::class)->run(User::factory()->auditor()->create(), app(GetStockTool::class), []);

    expect($record->status)->toBe(ToolCallStatus::Ok)
        ->and($record->data)->toBe(['items' => [], 'meta' => []]);
});

test('Herramientas con el rol del usuario: llamada negada sin consultar la base', function (Role $role, string $tool) {
    $user = User::factory()->withRole($role)->create();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $record = app(ReadOnlyToolRunner::class)->run($user, app($tool), ['warehouse' => 'farmacia central']);

    $queries = array_column(DB::getQueryLog(), 'query');
    expect($record->status)->toBe(ToolCallStatus::Denied)
        ->and($record->data)->toBeNull()
        ->and($record->arguments)->toBe(['warehouse' => 'farmacia central'])
        ->and($queries)->toBe([]);
})->with([
    'Médico pregunta por inventario' => [Role::Medico, GetStockTool::class],
    'Admin pregunta por inventario' => [Role::Admin, GetStockTool::class],
    'Médico pregunta por traslados' => [Role::Medico, GetTransferStatusTool::class],
    'Admin pregunta por traslados' => [Role::Admin, GetTransferStatusTool::class],
]);

test('los roles con la capacidad sí ejecutan (control positivo de la negación)', function (Role $role) {
    foreach ([GetStockTool::class, GetTransferStatusTool::class] as $tool) {
        expect(app(ReadOnlyToolRunner::class)->run(User::factory()->withRole($role)->create(), app($tool), [])->status)
            ->toBe(ToolCallStatus::Ok);
    }
})->with([Role::AuxiliarFarmacia, Role::RegenteFarmacia, Role::Auditor]);
