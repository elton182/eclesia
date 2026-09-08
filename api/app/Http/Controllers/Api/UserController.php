<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignUserRoleRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);
        $perPage = min((int) $request->query('per_page', 15), 100);

        return UserResource::collection($this->users->paginate($perPage));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($user, $request->validated()));
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return response()->noContent();
    }

    public function assignRole(AssignUserRoleRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        return new UserResource(
            $this->users->assignRole($user, $data['role'], $data['igreja_id'] ?? null)
        );
    }

    public function removeRole(AssignUserRoleRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        return new UserResource(
            $this->users->removeRole($user, $data['role'], $data['igreja_id'] ?? null)
        );
    }
}
