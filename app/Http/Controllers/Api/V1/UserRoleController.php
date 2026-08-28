<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\AssignRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function __construct(private readonly RoleAssignmentService $roles) {}

    public function index(User $user): JsonResponse
    {
        return $this->success(
            RoleResource::collection($user->roles()->orderBy('name')->get())
        );
    }

    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        $this->roles->assign($user, $request->role(), $request->user());

        return $this->success(
            RoleResource::collection($user->fresh()->roles()->orderBy('name')->get()),
            'Role berhasil ditambahkan.',
        );
    }

    public function destroy(Request $request, User $user, string $role): JsonResponse
    {
        $roleEnum = RoleEnum::tryFrom($role);

        abort_if($roleEnum === null, 404, 'Role tidak dikenal.');

        $this->roles->revoke($user, $roleEnum, $request->user());

        return $this->success(
            RoleResource::collection($user->fresh()->roles()->orderBy('name')->get()),
            'Role berhasil dicabut.',
        );
    }
}
