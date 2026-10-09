<?php

namespace App\Actions\Identity;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Alta de usuario por el admin: correo en minúsculas, contraseña con hash (cast `hashed`), rol explícito.
 * El alta y su fila `user.created` (actor = admin, objeto = usuario creado) van en una sola transacción: si la
 * fila no se escribe, no queda usuario (audit-trail "Bitácora de alta de usuarios", S10 D1). La fila lleva solo
 * ids: nunca nombre, correo, contraseña, hash ni rol.
 */
final class CreateUser
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function handle(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $user = new User([
                'name' => $data['name'],
                'email' => LoginAction::normalizeEmail($data['email']),
                'password' => $data['password'],
            ]);
            $user->role = Role::from($data['role']);
            $user->save();

            $this->audit->record($actor->id, AuditAction::UserCreated, $user->id);

            return $user;
        });
    }
}
