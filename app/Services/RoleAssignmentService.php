<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TokenDenyList;

/**
 * Perubahan role/2FA oleh Super Admin. Setiap perubahan mencabut token aktif
 * user (deny-list) + mencatat audit.
 */
class RoleAssignmentService
{
    public function __construct(
        private readonly TokenDenyList $denyList,
        private readonly AuditLogger $audit,
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function assign(User $user, Role $role, ?User $actor): void
    {
        if ($user->hasRole($role)) {
            return;
        }

        $user->assignRole($role);
        $this->denyList->revokeAllForUser($user->id);
        $this->audit->record(AuditEvent::RoleAssigned, $user, context: [
            'role' => $role->value,
            'by' => $actor?->getKey(),
        ]);
    }

    public function revoke(User $user, Role $role, ?User $actor): void
    {
        if (! $user->hasRole($role)) {
            return;
        }

        if ($role === Role::SuperAdmin) {
            if ($actor !== null && $actor->is($user)) {
                throw new BusinessException('Anda tidak bisa mencabut role Super Admin milik sendiri.', 422);
            }

            if ($this->superAdminCount() <= 1) {
                throw new BusinessException('Tidak bisa mencabut role Super Admin terakhir.', 422);
            }
        }

        $user->removeRole($role);
        $this->denyList->revokeAllForUser($user->id);
        $this->audit->record(AuditEvent::RoleRevoked, $user, context: [
            'role' => $role->value,
            'by' => $actor?->getKey(),
        ]);
    }

    public function resetTwoFactor(User $user, ?User $actor): void
    {
        $this->twoFactor->disable($user);
        $this->denyList->revokeAllForUser($user->id);
        $this->audit->record(AuditEvent::TwoFactorReset, $user, context: [
            'by' => $actor?->getKey(),
            'reason' => 'admin_reset',
        ]);
    }

    private function superAdminCount(): int
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::SuperAdmin->value))
            ->count();
    }
}
