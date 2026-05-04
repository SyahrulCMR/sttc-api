<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        return $this->success(
            UserResource::collection($this->userService->paginate($perPage))
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->created(new UserResource($user), 'User created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new UserResource($this->userService->find($id)));
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->update($id, $request->validated());

        return $this->success(new UserResource($user), 'User updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->userService->delete($id);

        return $this->success(message: 'User deleted successfully.');
    }
}
