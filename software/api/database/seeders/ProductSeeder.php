<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Productos genéricos sintéticos; uno solo de control especial (RN-05). Sin registros sanitarios.
     *
     * @var list<array{code: string, name: string, presentation: string, is_controlled: bool}>
     */
    public const PRODUCTS = [
        ['code' => 'MED-001', 'name' => 'Acetaminofén 500 mg', 'presentation' => 'Tableta, caja x 100', 'is_controlled' => false],
        ['code' => 'MED-002', 'name' => 'Amoxicilina 500 mg', 'presentation' => 'Cápsula, caja x 50', 'is_controlled' => false],
        ['code' => 'MED-003', 'name' => 'Ibuprofeno 400 mg', 'presentation' => 'Tableta, caja x 50', 'is_controlled' => false],
        ['code' => 'MED-004', 'name' => 'Losartán 50 mg', 'presentation' => 'Tableta, caja x 30', 'is_controlled' => false],
        ['code' => 'MED-005', 'name' => 'Omeprazol 20 mg', 'presentation' => 'Cápsula, caja x 30', 'is_controlled' => false],
        ['code' => 'MED-006', 'name' => 'Morfina 10 mg/mL', 'presentation' => 'Ampolla 1 mL', 'is_controlled' => true],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $product) {
            Product::query()->firstOrCreate(['code' => $product['code']], [
                'name' => $product['name'],
                'presentation' => $product['presentation'],
                'is_controlled' => $product['is_controlled'],
            ]);
        }
    }
}
