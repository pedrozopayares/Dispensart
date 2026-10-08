<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario en listados de administración: sin contraseña ni token.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, email: string, role: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
        ];
    }
}
