<?php

namespace App\Http\Controllers\Users;

use App\Actions\Identity\CreateUser;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Queries\CatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserController
{
    /**
     * Usuarios ordenados por nombre (solo admin).
     */
    public function index(CatalogQuery $query): AnonymousResourceCollection
    {
        return UserResource::collection($query->users());
    }

    /**
     * Crea un usuario con un rol (solo admin).
     */
    public function store(StoreUserRequest $request, CreateUser $createUser): UserResource
    {
        /** @var array{name: string, email: string, password: string, role: string} $data */
        $data = $request->validated();

        return new UserResource($createUser->handle($data));
    }
}
