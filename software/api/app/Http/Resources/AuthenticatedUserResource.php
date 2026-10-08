<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario de la sesión con sus capacidades (informativas para la SPA; el servidor autoriza cada petición).
 *
 * @mixin User
 */
final class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, email: string, role: string, abilities: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'abilities' => $this->role->abilityValues(),
        ];
    }
}
