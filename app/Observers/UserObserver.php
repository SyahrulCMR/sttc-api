<?php

namespace App\Observers;

use App\Enums\AuditEvent;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TokenDenyList;

/**
 * Cabut seluruh token aktif user saat akun tidak lagi bisa dipakai + catat audit.
 * (Penulis deny-list lain: broadcastLogout, manajemen role, reset 2FA, break-glass.)
 */
class UserObserver
{
    public function __construct(
        private readonly TokenDenyList $denyList,
        private readonly AuditLogger $audit,
    ) {}

    public function updating(User $user): void
    {
        // Jejak "kapan terakhir ganti kata sandi" (dipakai relock break-glass).
        if ($user->isDirty('password') && ! $user->isDirty('password_changed_at')) {
            $user->password_changed_at = now();
        }
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('status') && $user->status !== UserStatus::Active) {
            $this->denyList->revokeAllForUser($user->id);
            $this->audit->record(AuditEvent::AccountSuspended, $user, context: ['status' => $user->status->value]);
            $this->audit->record(AuditEvent::TokenRevoked, $user, context: ['reason' => 'status_change']);
        }
    }

    public function deleted(User $user): void
    {
        $this->denyList->revokeAllForUser($user->id);
        $this->audit->record(AuditEvent::TokenRevoked, $user, context: ['reason' => 'soft_delete']);
    }
}
