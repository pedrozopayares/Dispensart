<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /** @var list<array{code: string, name: string}> */
    public const WAREHOUSES = [
        ['code' => 'FC', 'name' => 'Farmacia Central'],
        ['code' => 'FU', 'name' => 'Farmacia Urgencias'],
        ['code' => 'BH', 'name' => 'Bodega Hospitalización'],
    ];

    public function run(): void
    {
        foreach (self::WAREHOUSES as $warehouse) {
            // Clave natural: código. Si existe (aunque el admin lo haya renombrado), no se toca.
            Warehouse::query()->firstOrCreate(['code' => $warehouse['code']], ['name' => $warehouse['name']]);
        }
    }
}
