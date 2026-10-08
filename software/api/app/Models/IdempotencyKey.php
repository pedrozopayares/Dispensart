<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Respuesta exitosa guardada para una clave de idempotencia de un usuario (RN-09, design D5).
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $request_hash
 * @property int $response_status
 * @property string $response_body
 * @property CarbonImmutable $created_at
 */
class IdempotencyKey extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
