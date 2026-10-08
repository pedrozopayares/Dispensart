<?php

namespace Database\Seeders;

use App\Models\Lot;
use App\Models\Product;
use App\Support\BusinessCalendar;
use Illuminate\Database\Seeder;

class LotSeeder extends Seeder
{
    /**
     * Lotes por código de producto: [código de lote, días hasta el vencimiento desde hoy en Bogotá].
     * Cubre vencido, 1–29 días, 31–90 días y más de 90 días (seed-data "Distribución de vencimientos").
     *
     * @var array<string, list<array{0: string, 1: int}>>
     */
    public const LOTS = [
        'MED-001' => [['L-ACE-2401', -10], ['L-ACE-2402', 20], ['L-ACE-2403', 200]],
        'MED-002' => [['L-AMX-2401', 45], ['L-AMX-2402', 365]],
        'MED-003' => [['L-IBU-2401', 15], ['L-IBU-2402', 120]],
        'MED-004' => [['L-LOS-2401', -30], ['L-LOS-2402', 60], ['L-LOS-2403', 400]],
        'MED-005' => [['L-OMP-2401', 75], ['L-OMP-2402', 300]],
        'MED-006' => [['L-MOR-2401', 10], ['L-MOR-2402', 150]],
    ];

    public function run(): void
    {
        $today = BusinessCalendar::today();

        foreach (self::LOTS as $productCode => $lots) {
            $productId = Product::query()->where('code', $productCode)->value('id');
            if ($productId === null) {
                continue;
            }

            foreach ($lots as [$lotCode, $days]) {
                Lot::query()->firstOrCreate(
                    ['product_id' => $productId, 'lot_code' => $lotCode],
                    ['expires_on' => $today->addDays($days)->toDateString()],
                );
            }
        }
    }
}
