<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Traslados sintéticos sembrables directamente en cada uno de los 7 estados, con actores coherentes:
 * creador auxiliar, solicitante = creador, aprobador regente distinto. Sin movimientos de kardex: el estado se
 * fija, no se recorre (para recorrerlo están las acciones).
 *
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'origin_warehouse_id' => Warehouse::factory(),
            'destination_warehouse_id' => Warehouse::factory(),
            'status' => TransferStatus::Draft,
            'notes' => null,
            'created_by' => User::factory()->auxiliar(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => TransferStatus::Draft]);
    }

    public function requested(): static
    {
        return $this->state([
            'status' => TransferStatus::Requested,
            'requested_by' => fn (array $attributes): int => $attributes['created_by'],
            'requested_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->requested()->state([
            'status' => TransferStatus::Approved,
            'approved_by' => User::factory()->regente(),
            'approved_at' => now(),
        ]);
    }

    public function inTransit(): static
    {
        return $this->approved()->state([
            'status' => TransferStatus::InTransit,
            'dispatched_by' => fn (array $attributes): int => $attributes['created_by'],
            'dispatched_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->inTransit()->state([
            'status' => TransferStatus::Received,
            'received_by' => fn (array $attributes): int => $attributes['created_by'],
            'received_at' => now(),
        ]);
    }

    public function partiallyReceived(): static
    {
        return $this->received()->state(['status' => TransferStatus::PartiallyReceived]);
    }

    /**
     * Anulado desde SOLICITADO por su creador.
     */
    public function voided(): static
    {
        return $this->requested()->state([
            'status' => TransferStatus::Voided,
            'voided_by' => fn (array $attributes): int => $attributes['created_by'],
            'voided_at' => now(),
            'void_reason' => 'Anulación sintética',
        ]);
    }

    public function inStatus(TransferStatus $status): static
    {
        return match ($status) {
            TransferStatus::Draft => $this->draft(),
            TransferStatus::Requested => $this->requested(),
            TransferStatus::Approved => $this->approved(),
            TransferStatus::InTransit => $this->inTransit(),
            TransferStatus::Received => $this->received(),
            TransferStatus::PartiallyReceived => $this->partiallyReceived(),
            TransferStatus::Voided => $this->voided(),
        };
    }
}
