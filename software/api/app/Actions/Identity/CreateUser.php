<?php

namespace App\Actions\Identity;

use App\Enums\Role;
use App\Models\User;

/**
 * Alta de usuario por el admin: correo en minúsculas, contraseña con hash (cast `hashed`), rol explícito.
 */
final class CreateUser
{
    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function handle(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => LoginAction::normalizeEmail($data['email']),
            'password' => $data['password'],
        ]);
        $user->role = Role::from($data['role']);
        $user->save();

        return $user;
    }
}
