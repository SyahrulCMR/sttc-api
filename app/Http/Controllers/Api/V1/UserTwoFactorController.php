<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserTwoFactorController extends Controller
{
    public function __construct(private readonly RoleAssignmentService $roles) {}

    public function reset(Request $request, User $user): JsonResponse
    {
        $this->roles->resetTwoFactor($user, $request->user());

        return $this->success(message: '2FA akun berhasil direset. User wajib mendaftar ulang saat login berikutnya.');
    }
}
