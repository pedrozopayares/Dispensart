<?php

namespace App\Actions\Prescriptions;

use App\Enums\AuditAction;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Alta de prescripción (prescriptions "Creación de prescripciones por el médico", RN-04): el médico es el
 * usuario autenticado, los ítems nacen con dispensada 0 y la fila prescription.created va en la misma
 * transacción.
 */
final class CreatePrescription
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  array{patient_id: int, valid_until: string, items: list<array{product_id: int, quantity: int}>}  $data
     */
    public function handle(User $prescriber, array $data): Prescription
    {
        return DB::transaction(function () use ($prescriber, $data): Prescription {
            $prescription = Prescription::query()->create([
                'patient_id' => $data['patient_id'],
                'prescriber_id' => $prescriber->id,
                'valid_until' => $data['valid_until'],
            ]);

            foreach ($data['items'] as $item) {
                $prescription->items()->create(['product_id' => $item['product_id'], 'prescribed_quantity' => $item['quantity']]);
            }

            $this->audit->record($prescriber->id, AuditAction::PrescriptionCreated, $prescription->id, ['patient_id' => $data['patient_id']]);

            return $prescription->refresh()->load(['prescriber:id,name', 'items.product']);
        });
    }
}
