<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Siembra sintética e idempotente (seed-data, design D11). Cada fila se busca por clave natural y solo
 * se crea si falta: nunca duplica, borra ni modifica lo existente (cambios del admin incluidos).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            WarehouseSeeder::class,
            ProductSeeder::class,
            LotSeeder::class,
            StockSeeder::class,
            UserSeeder::class,
        ]);
    }
}
