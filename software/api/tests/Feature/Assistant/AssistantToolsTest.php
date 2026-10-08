<?php

use App\Enums\Role;
use App\Services\Assistant\ToolCallStatus;
use App\Services\Assistant\Tools\FindExpiringLotsTool;
use App\Services\Assistant\Tools\GetLowStockAlertsTool;
use App\Services\Assistant\Tools\GetStockTool;
use App\Services\Assistant\Tools\GetTransferStatusTool;
use App\Services\Assistant\Tools\ReadOnlyToolRunner;
use Database\Seeders\AssistantEvalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Las 4 herramientas a nivel de herramienta (inventory-assistant), con reloj fijado y el mundo de AssistantEvalSeeder
// (tabla de datos en su docblock). Cada una corre por ReadOnlyToolRunner con un auditor, como en la ruta.

beforeEach(function () {
    freezeAlertClock();
    $this->world = assistantWorld();
});

/**
 * @param  array<string, mixed>  $arguments
 * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
 */
function runTool(string $tool, array $arguments = []): array
{
    $record = app(ReadOnlyToolRunner::class)->run(worldUser(test()->world, Role::Auditor), app($tool), $arguments);
    expect($record->status)->toBe(ToolCallStatus::Ok);

    return $record->data;
}

function lotCodes(array $items): array
{
    return array_column($items, 'lot_code');
}

describe('find_expiring_lots', function () {
    test('Pregunta de ejemplo de la parte C: plazo, producto y bodega filtran', function () {
        $data = runTool(FindExpiringLotsTool::class, ['days' => 60, 'product' => 'acetaminofén', 'warehouse' => 'farmacia central']);

        expect(lotCodes($data['items']))->toBe(['EVAL-ACE-020'])
            ->and($data['items'][0])->toBe([
                'warehouse' => 'Farmacia Central', 'product' => 'Acetaminofén 500 mg', 'lot_code' => 'EVAL-ACE-020',
                'expires_on' => '2027-04-03', 'quantity' => 10, 'is_expired' => false,
            ])
            ->and($data['meta'])->toBe(['days' => 60]);
    });

    test('Plazo no indicado: 90 días, sin filtro de producto ni de bodega', function () {
        $data = runTool(FindExpiringLotsTool::class);

        expect($data['meta'])->toBe(['days' => 90])
            ->and(lotCodes($data['items']))->toBe(['EVAL-IBU-VEN', 'EVAL-ACE-020', 'EVAL-ACE-URG', 'EVAL-ACE-075'])
            ->and(array_column($data['items'], 'warehouse'))->toContain('Farmacia Urgencias', 'Farmacia Central');
    });

    test('Lote vencido con existencia: incluido y marcado', function () {
        $items = runTool(FindExpiringLotsTool::class, ['days' => 30])['items'];
        $expired = array_values(array_filter($items, fn ($item) => $item['lot_code'] === 'EVAL-IBU-VEN'));

        expect($expired)->toHaveCount(1)
            ->and($expired[0]['is_expired'])->toBeTrue()
            ->and($expired[0]['quantity'])->toBe(5)
            ->and(lotCodes($items))->not->toContain('EVAL-ACE-075');
    });

    test('Producto inexistente: sin resultados y sin "el primero"', function (string $product) {
        expect(runTool(FindExpiringLotsTool::class, ['product' => $product])['items'])->toBe([]);
    })->with(['zzzmedicamento', 'mg']);

    test('producto y bodega sin tildes ni mayúsculas resuelven igual', function () {
        expect(runTool(FindExpiringLotsTool::class, ['product' => 'ACETAMINOFEN', 'warehouse' => 'FARMACIA DE URGENCIAS']))
            ->toBe(runTool(FindExpiringLotsTool::class, ['product' => 'acetaminofén', 'warehouse' => 'Farmacia Urgencias']))
            ->and(lotCodes(runTool(FindExpiringLotsTool::class, ['warehouse' => 'farmacia de urgencias'])['items']))
            ->toBe(['EVAL-IBU-VEN', 'EVAL-ACE-URG']);
    });

    test('bodega ambigua: resultado vacío, nunca la primera coincidencia', function () {
        expect(runTool(FindExpiringLotsTool::class, ['warehouse' => 'farmacia'])['items'])->toBe([]);
    });
});

describe('get_stock', function () {
    test('Total disponible sin vencidos: 10 disponibles aunque haya 5 vencidas', function () {
        $items = runTool(GetStockTool::class, ['product' => 'ibuprofeno', 'warehouse' => 'farmacia de urgencias'])['items'];

        expect($items)->toHaveCount(1)
            ->and($items[0]['available_total'])->toBe(10)
            ->and(array_sum(array_column($items[0]['lots'], 'quantity')))->toBe(15)
            ->and(array_column($items[0]['lots'], 'is_expired'))->toBe([true, false]);
    });

    test('Pregunta respondida: 30 unidades de acetaminofén en Farmacia Central', function () {
        $items = runTool(GetStockTool::class, ['product' => 'acetaminofén', 'warehouse' => 'farmacia central'])['items'];

        expect($items)->toHaveCount(1)
            ->and($items[0]['available_total'])->toBe(30)
            ->and($items[0]['warehouse'])->toBe('Farmacia Central');
    });

    test('Producto sin existencias en la bodega: sin resultados', function () {
        expect(runTool(GetStockTool::class, ['product' => 'amoxicilina', 'warehouse' => 'farmacia de urgencias'])['items'])->toBe([])
            // Control positivo: la amoxicilina sí está en Central.
            ->and(runTool(GetStockTool::class, ['product' => 'amoxicilina', 'warehouse' => 'farmacia central'])['items'])->toHaveCount(1);
    });
});

describe('get_low_stock_alerts', function () {
    test('Producto bajo su mínimo: producto, bodega, disponible y mínimo', function () {
        expect(runTool(GetLowStockAlertsTool::class)['items'])->toBe([
            ['warehouse' => 'Farmacia Central', 'product' => 'Amoxicilina 500 mg', 'minimum' => 10, 'available' => 4],
        ]);
    });

    test('Existencia igual al mínimo: sin resultados en Urgencias', function () {
        expect(runTool(GetLowStockAlertsTool::class, ['warehouse' => 'farmacia de urgencias'])['items'])->toBe([])
            ->and(runTool(GetLowStockAlertsTool::class, ['warehouse' => 'farmacia central'])['items'])->toHaveCount(1);
    });
});

describe('get_transfer_status', function () {
    test('Traslado recibido parcialmente: estado, líneas y discrepancia pendiente', function () {
        $items = runTool(GetTransferStatusTool::class, ['transfer_id' => $this->world['transfers']['partial']])['items'];

        expect($items)->toHaveCount(1)
            ->and($items[0]['status'])->toBe('RECIBIDO_PARCIAL')
            ->and($items[0]['lines'])->toBe([['product' => 'Losartán 50 mg', 'lot_code' => 'EVAL-LOS-300', 'quantity' => 5, 'received_quantity' => 3]])
            ->and($items[0]['pending_discrepancies'])->toBe([['product' => 'Losartán 50 mg', 'lot_code' => 'EVAL-LOS-300', 'shortage' => 2]]);
    });

    test('Conteo por estado: 2 en tránsito; sin filtro, todos los estados presentes', function () {
        expect(runTool(GetTransferStatusTool::class, ['status' => 'EN_TRANSITO']))
            ->toBe(['items' => [['status' => 'EN_TRANSITO', 'count' => 2]], 'meta' => ['mode' => 'counts']])
            ->and(runTool(GetTransferStatusTool::class)['items'])->toBe([
                ['status' => 'BORRADOR', 'count' => 1],
                ['status' => 'SOLICITADO', 'count' => 1],
                ['status' => 'EN_TRANSITO', 'count' => 2],
                ['status' => 'RECIBIDO_PARCIAL', 'count' => 1],
            ])
            ->and(runTool(GetTransferStatusTool::class, ['warehouse' => 'farmacia de urgencias'])['items'])->toBe([])
            ->and(array_sum(array_column(runTool(GetTransferStatusTool::class, ['warehouse' => 'bodega hospitalizacion'])['items'], 'count')))->toBe(5);
    });

    test('Traslado inexistente: sin resultados', function () {
        expect(runTool(GetTransferStatusTool::class, ['transfer_id' => 999999])['items'])->toBe([]);
    });

    test('lista blanca: notes como texto no confiable, sin usuarios ni actores', function () {
        $item = runTool(GetTransferStatusTool::class, ['transfer_id' => $this->world['transfers']['malicious']])['items'][0];

        expect(array_keys($item))->toBe(['id', 'status', 'origin_warehouse', 'destination_warehouse', 'lines', 'pending_discrepancies', 'notes'])
            ->and($item['notes'])->toBe(['untrusted_text' => AssistantEvalSeeder::MALICIOUS_NOTE])
            ->and(json_encode($item))->not->toContain('@dispensart.test')->not->toContain('password')->not->toContain('created_by');
    });
});
