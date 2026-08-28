<?php

namespace App\Console\Commands;

use App\Enums\AuditEvent;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan scheduler tiap jam. Mengunci akun Super Admin yang sudah melewati
 * `break_glass_at` + N jam tetapi belum ganti kata sandi & mengaktifkan 2FA lagi.
 */
class EnforceBreakGlassRelockCommand extends Command
{
    protected $signature = 'sso:enforce-break-glass-relock';

    protected $description = 'Kunci akun pasca break-glass yang belum diremediasi setelah batas waktu';

    public function handle(AuditLogger $audit): int
    {
        $deadline = now()->subHours((int) config('security.break_glass_relock_hours', 24));

        $overdue = User::query()
            ->whereNotNull('break_glass_at')
            ->where('break_glass_at', '<=', $deadline)
            ->get();

        foreach ($overdue as $user) {
            if ($this->remediated($user)) {
                $user->forceFill(['break_glass_at' => null])->save();
                $this->info("Akun {$user->identifier} sudah diremediasi — penanda break-glass dibersihkan.");

                continue;
            }

            $user->forceFill([
                'status' => UserStatus::Suspended,
                'break_glass_at' => null,
            ])->save();

            $audit->record(AuditEvent::BreakGlassRelock, $user);

            Log::channel(config('logging.default'))->warning('Akun dikunci pasca break-glass (tidak diremediasi)', [
                'user_id' => $user->id,
                'identifier' => $user->identifier,
            ]);

            $this->warn("Akun {$user->identifier} dikunci (suspended).");
        }

        return self::SUCCESS;
    }

    private function remediated(User $user): bool
    {
        $breakGlassAt = $user->break_glass_at;

        return (bool) $user->password_changed_at?->gt($breakGlassAt)
            && (bool) $user->two_factor_confirmed_at?->gt($breakGlassAt);
    }
}
