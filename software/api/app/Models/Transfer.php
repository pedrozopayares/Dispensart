<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Traslado entre bodegas (RN-07, RN-08). Solo las acciones de App\Actions\Transfers cambian su estado, siempre
 * bajo el bloqueo de esta fila (design D4) y contra TransferTransitions. created_by es inmutable.
 *
 * @property int $id
 * @property int $origin_warehouse_id
 * @property int $destination_warehouse_id
 * @property TransferStatus $status
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property int|null $dispatched_by
 * @property int|null $received_by
 * @property int|null $voided_by
 * @property CarbonImmutable|null $requested_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $dispatched_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<TransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class)->orderBy('id');
    }

    /**
     * @return HasMany<TransferDiscrepancy, $this>
     */
    public function discrepancies(): HasMany
    {
        return $this->hasMany(TransferDiscrepancy::class)->orderBy('id');
    }

    /**
     * Filtros opcionales combinados con Y (transfers "Consulta de traslados").
     *
     * @param  Builder<Transfer>  $query
     * @param  array{status?: string, origin_warehouse_id?: int, destination_warehouse_id?: int}  $filters
     * @return Builder<Transfer>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $column => $value) {
            $query->where($this->qualifyColumn($column), $value);
        }

        return $query;
    }
}
