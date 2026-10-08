<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Una prescripción vigente del médico semilla por paciente semilla (prescriptions "Prescripciones semilla");
 * exactamente una incluye el producto de control especial (MED-006). valid_until relativo a la siembra.
 * Idempotente: si el paciente ya tiene una prescripción del médico semilla, no se toca (ni sus saldos).
 */
class PrescriptionSeeder extends Seeder
{
    public const VALID_DAYS = 30;

    /**
     * Ítems por documento de paciente: [código de producto, cantidad prescrita].
     *
     * @var array<string, list<array{0: string, 1: int}>>
     */
    public const PRESCRIPTIONS = [
        '9999010001' => [['MED-001', 10], ['MED-003', 5]],
        '9999010002' => [['MED-006', 2], ['MED-002', 6]],
        '9999010003' => [['MED-004', 30]],
    ];

    public function run(): void
    {
        $prescriberId = User::query()->where('email', 'medico@dispensart.test')->value('id');
        if ($prescriberId === null) {
            return;
        }

        foreach (self::PRESCRIPTIONS as $documentNumber => $items) {
            $patientId = Patient::query()->where('document_number', $documentNumber)->value('id');
            if ($patientId === null) {
                continue;
            }

            $exists = Prescription::query()
                ->where('patient_id', $patientId)
                ->where('prescriber_id', $prescriberId)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::transaction(function () use ($patientId, $prescriberId, $items): void {
                $prescription = Prescription::query()->create([
                    'patient_id' => $patientId,
                    'prescriber_id' => $prescriberId,
                    'valid_until' => BusinessCalendar::today()->addDays(self::VALID_DAYS)->toDateString(),
                ]);

                foreach ($items as [$productCode, $quantity]) {
                    $productId = Product::query()->where('code', $productCode)->value('id');
                    if ($productId !== null) {
                        $prescription->items()->create(['product_id' => $productId, 'prescribed_quantity' => $quantity]);
                    }
                }
            });
        }
    }
}
