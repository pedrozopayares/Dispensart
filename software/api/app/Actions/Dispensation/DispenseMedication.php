<?php

namespace App\Actions\Dispensation;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Exceptions\AuthorizationRequired;
use App\Exceptions\InsufficientStock;
use App\Http\Resources\DispensationResource;
use App\Models\Dispensation;
use App\Models\DispensationLine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Services\Dispensation\ControlledDrugAuthorizer;
use App\Services\Dispensation\DispensationPlan;
use App\Services\Dispensation\DispensationPlanner;
use App\Services\Dispensation\StockCandidates;
use App\Services\Idempotency\IdempotencyStore;
use App\Services\Idempotency\StoredResponse;
use App\Services\Inventory\StockChange;
use App\Services\Inventory\StockLedger;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\DB;

/**
 * Dispensación FEFO, atómica, concurrente e idempotente (RN-01..RN-06, RN-09; design D2–D6). Precedencia de
 * rechazos tras validar (design D4): repetición rápida → autorizador (fuera de la transacción) → en una
 * transacción: candado de la clave + relectura → ítems de la prescripción bloqueados por id → estado →
 * pendiente → is_controlled releído → existencias bloqueadas en la clave global + FEFO → escrituras.
 * Orden de bloqueo global: clave → prescription_items → stocks; ajustes (S2) y traslados (S4) solo toman
 * stocks con la misma clave: sin ciclos.
 */
final class DispenseMedication
{
    public function __construct(
        private readonly IdempotencyStore $idempotency,
        private readonly ControlledDrugAuthorizer $authorizer,
        private readonly DispensationPlanner $planner,
        private readonly StockCandidates $candidates,
        private readonly StockLedger $ledger,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @param  array{prescription_id: int, warehouse_id: int, items: list<array{prescription_item_id: int, quantity: int}>}  $data
     */
    public function handle(
        User $dispenser,
        array $data,
        ?string $authorizerEmail,
        ?string $authorizerPassword,
        string $key,
        string $requestHash,
    ): StoredResponse {
        // Paso 5: un reintento no vuelve a verificar credenciales ni gasta intentos del limitador.
        $replay = $this->idempotency->find($dispenser, $key, $requestHash);
        if ($replay !== null) {
            return $replay;
        }

        // Paso 6: bcrypt fuera de la transacción; la fila de fallo queda confirmada aunque se rechace.
        $authorizer = null;
        if ($this->requestsControlledProduct($data)) {
            $authorizer = $this->authorizer->verify(
                $dispenser, $authorizerEmail, $authorizerPassword, $data['prescription_id'], $data['warehouse_id'],
            );
        }

        return DB::transaction(function () use ($dispenser, $data, $authorizer, $key, $requestHash): StoredResponse {
            // Paso 7: dos reintentos simultáneos se serializan aquí; el segundo relee y repite.
            $this->idempotency->lock($dispenser, $key);
            $replay = $this->idempotency->find($dispenser, $key, $requestHash);
            if ($replay !== null) {
                return $replay;
            }

            $plan = $this->planUnderLock($data, $authorizer);
            $dispensation = $this->write($dispenser, $authorizer, $plan);

            $body = (string) json_encode(['data' => (new DispensationResource($dispensation))->resolve()]);
            $response = new StoredResponse(201, $body, replayed: false);
            $this->idempotency->remember($dispenser, $key, $requestHash, $response);

            return $response;
        });
    }

    /**
     * Pasos 8–11, dentro de la transacción y con filas bloqueadas.
     *
     * @param  array{prescription_id: int, warehouse_id: int, items: list<array{prescription_item_id: int, quantity: int}>}  $data
     */
    private function planUnderLock(array $data, ?User $authorizer): DispensationPlan
    {
        $prescription = Prescription::query()->findOrFail($data['prescription_id']);
        // Saldos frescos: con el bloqueo, una dispensación concurrente ya confirmada se ve aquí.
        $items = PrescriptionItem::query()
            ->where('prescription_id', $prescription->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->load('product');
        $today = BusinessCalendar::today();

        $this->planner->check($prescription, $items, $data['items'], $today);

        $requestedIds = array_column($data['items'], 'prescription_item_id');
        $requestedItems = $items->whereIn('id', $requestedIds);
        // Paso 10: is_controlled es editable; se relee aquí para cerrar la ventana desde el paso 6.
        if ($authorizer === null && $requestedItems->contains(fn (PrescriptionItem $item): bool => $item->product->is_controlled)) {
            throw new AuthorizationRequired;
        }

        $candidates = $this->candidates->for(
            $data['warehouse_id'], array_values($requestedItems->pluck('product_id')->all()), lock: true,
        );
        $plan = $this->planner->allocate($prescription, $items, $data['items'], $data['warehouse_id'], $candidates, $today);

        if (! $plan->fulfillable()) {
            throw new InsufficientStock($plan->shortages());
        }

        return $plan;
    }

    /**
     * Paso 12: dispensación → un salida_dispensacion por lote (StockLedger) → líneas enlazadas a su movimiento →
     * saldos de ítems con incremento relativo (la base impide superar lo prescrito) → bitácora.
     */
    private function write(User $dispenser, ?User $authorizer, DispensationPlan $plan): Dispensation
    {
        $authorizedBy = $plan->requiresAuthorization() ? $authorizer?->id : null;

        $dispensation = (new Dispensation)->forceFill([
            'prescription_id' => $plan->prescription->id,
            'patient_id' => $plan->prescription->patient_id,
            'warehouse_id' => $plan->warehouseId,
            'dispensed_by' => $dispenser->id,
            'authorized_by' => $authorizedBy,
        ]);
        $dispensation->save();

        $changes = [];
        $sources = [];
        foreach ($plan->items as $itemPlan) {
            foreach ($itemPlan->allocation->lines as $line) {
                $changes[] = new StockChange(
                    $plan->warehouseId, $line->lotId, -$line->quantity, MovementType::DispensingOutbound, $dispenser->id,
                );
                $sources[] = $itemPlan->item;
            }
        }

        // apply() devuelve los movimientos en el orden de los cambios (design D2).
        foreach ($this->ledger->apply($changes) as $index => $movement) {
            (new DispensationLine)->forceFill([
                'dispensation_id' => $dispensation->id,
                'prescription_id' => $plan->prescription->id,
                'prescription_item_id' => $sources[$index]->id,
                'product_id' => $movement->product_id,
                'lot_id' => $movement->lot_id,
                'quantity' => -$movement->quantity,
                'kardex_movement_id' => $movement->id,
            ])->save();
        }

        foreach ($plan->items as $itemPlan) {
            DB::update(
                'UPDATE prescription_items SET dispensed_quantity = dispensed_quantity + ?, updated_at = now() WHERE id = ?',
                [$itemPlan->requested, $itemPlan->item->id],
            );
        }

        $details = ['prescription_id' => $plan->prescription->id, 'warehouse_id' => $plan->warehouseId];
        $this->audit->record($dispenser->id, AuditAction::DispensationCreated, $dispensation->id, $details);
        if ($authorizedBy !== null) {
            $this->audit->record($authorizedBy, AuditAction::ControlledDrugAuthorized, $dispensation->id, $details);
        }

        return $dispensation->refresh()->load('lines.lot');
    }

    /**
     * ¿Algún ítem pedido es de control especial? Lectura sin bloqueo para decidir el paso 6; el paso 10 la repite.
     *
     * @param  array{prescription_id: int, warehouse_id: int, items: list<array{prescription_item_id: int, quantity: int}>}  $data
     */
    private function requestsControlledProduct(array $data): bool
    {
        return PrescriptionItem::query()
            ->whereIn('id', array_column($data['items'], 'prescription_item_id'))
            ->whereHas('product', fn ($query) => $query->where('is_controlled', true))
            ->exists();
    }
}
