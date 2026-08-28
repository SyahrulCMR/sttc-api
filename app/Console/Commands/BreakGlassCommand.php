<?php

namespace App\Console\Commands;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\BreakGlassCredentialNotification;
use App\Notifications\BreakGlassNoticeNotification;
use App\Support\AuditLogger;
use App\Support\TokenDenyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prosedur darurat pemulihan akun Super Admin (epics/sprint-1-plan.md §5.5).
 *
 * HANYA di server: butuh --force DAN env BREAK_GLASS_ENABLED=true.
 */
class BreakGlassCommand extends Command
{
    protected $signature = 'sso:break-glass {identifier : NIM/NIDN Super Admin} {--force : Wajib, konfirmasi eksekusi}';

    protected $description = 'Reset darurat akun Super Admin (kata sandi sementara + nonaktifkan 2FA + cabut token)';

    public function handle(TokenDenyList $denyList, AuditLogger $audit): int
    {
        if (! $this->option('force') || ! config('security.break_glass_enabled')) {
            $this->error('Ditolak. Perlu --force DAN BREAK_GLASS_ENABLED=true (di-set hanya di server).');

            return self::FAILURE;
        }

        $user = User::query()->where('identifier', $this->argument('identifier'))->first();

        if (! $user || ! $user->hasRole(Role::SuperAdmin)) {
            $this->error('Akun tidak ditemukan atau bukan Super Admin.');

            return self::FAILURE;
        }

        $temporaryPassword = Str::password(16);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // break_glass_at ditetapkan SETELAH reset -> timestamp password/2FA < break_glass_at.
        DB::table('users')->where('id', $user->id)->update(['break_glass_at' => now()]);

        $denyList->revokeAllForUser($user->id);

        $relockHours = (int) config('security.break_glass_relock_hours', 24);
        $user->notify(new BreakGlassCredentialNotification($temporaryPassword, $relockHours));

        User::query()
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::SuperAdmin->value))
            ->get()
            ->each->notify(new BreakGlassNoticeNotification($user));

        $audit->record(AuditEvent::BreakGlass, $user);
        $audit->record(AuditEvent::TwoFactorReset, $user, context: ['reason' => 'break_glass']);

        Log::channel(config('logging.default'))->warning('BREAK-GLASS dijalankan', [
            'user_id' => $user->id,
            'identifier' => $user->identifier,
        ]);

        $this->info("Break-glass selesai untuk {$user->identifier}. Kata sandi sementara dikirim ke email akun.");
        $this->warn("Akun akan dikunci otomatis dalam {$relockHours} jam bila kata sandi tidak diganti & 2FA tidak diaktifkan kembali.");

        return self::SUCCESS;
    }
}
