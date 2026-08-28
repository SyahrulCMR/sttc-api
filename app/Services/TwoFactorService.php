<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;

/**
 * 2FA (TOTP, RFC 6238) — enrollment, verifikasi, recovery code.
 * Kolom mengikuti konvensi Fortify (`two_factor_*`), lihat migration 1a-3.
 */
class TwoFactorService
{
    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->engine->verifyKey($secret, preg_replace('/\s+/', '', $code), 1);
    }

    /**
     * QR code sebagai data-URI SVG (dirender lokal, tanpa aset eksternal).
     */
    public function qrCodeDataUri(string $accountLabel, string $secret): string
    {
        $svg = (new Google2FAQRCode)->getQRCodeInline(
            (string) config('app.name', 'STTC'),
            $accountLabel,
            $secret,
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return list<string>
     */
    public function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->values()
            ->all();
    }

    /**
     * Simpan secret + recovery codes, tandai terkonfirmasi.
     *
     * @return list<string> recovery codes (plaintext — ditampilkan sekali)
     */
    public function confirm(User $user, string $secret): array
    {
        $codes = $this->newRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $target = Str::upper(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        $match = collect($codes)->first(fn (string $c) => Str::upper($c) === $target);

        if ($match === null) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => collect($codes)
                ->reject(fn (string $c) => Str::upper($c) === $target)
                ->values()
                ->all(),
        ])->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
