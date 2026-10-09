<?php

namespace App\Http\Controllers\Users;

use App\Actions\Identity\CreateUser;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\CatalogQuery;
use Illuminate\Http\JsonResponse;
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
    public function store(StoreUserRequest $request, CreateUser $createUser): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, role: string} $data */
        $data = $request->validated();
        /** @var User $actor */
        $actor = $request->user();

        return (new UserResource($createUser->handle($actor, $data)))->response()->setStatusCode(201);
    }
}
