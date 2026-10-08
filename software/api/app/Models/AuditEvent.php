<?php

namespace App\Models;

use App\Enums\AuditAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila de la bitácora de operaciones sensibles (RN-05, RN-10). Solo inserción; el detalle solo admite ids.
 *
 * @property int $id
 * @property int $actor_id
 * @property AuditAction $action
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string, int> $details
 * @property string|null $correlation_id
 * @property CarbonImmutable $created_at
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'details' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
