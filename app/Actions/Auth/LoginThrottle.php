<?php

namespace App\Actions\Auth;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Throttle/lockout login berlapis (epics/sprint-1-plan.md §5.2, config/security.php).
 *
 * Dipakai bersama oleh alur SSO web login lama & alur OAuth authorize (1a-9).
 */
class LoginThrottle
{
    private RateLimiter $limiter;

    public function __construct()
    {
        $store = config('security.store', 'throttle');

        try {
            $this->limiter = new RateLimiter(Cache::store($store));
        } catch (\Throwable $e) {
            // Store tak bisa di-resolve -> fail-open (limiter dummy pada store array).
            Log::warning("LoginThrottle: gagal resolve store '{$store}', fail-open. {$e->getMessage()}");
            $this->limiter = new RateLimiter(Cache::store('array'));
        }
    }

    /**
     * Lempar ValidationException bila salah satu lapis (L1/L2/L3) terkunci.
     */
    public function assertNotLocked(string $identifier, string $ip): void
    {
        $checks = [
            $this->key('l1', $identifier, $ip) => 'identifier_ip',
            $this->key('l2', $ip) => 'ip',
            $this->key('l3', $identifier) => 'identifier',
        ];

        $lockedFor = 0;

        foreach ($checks as $key => $layer) {
            $max = (int) config("security.layers.$layer.max_attempts");

            if ($this->safely(fn () => $this->limiter->tooManyAttempts($key, $max), false)) {
                $lockedFor = max($lockedFor, $this->safely(fn () => $this->limiter->availableIn($key), 60));
            }
        }

        if ($lockedFor > 0) {
            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan login. Coba lagi dalam {$lockedFor} detik.",
            ])->status(429);
        }
    }

    /**
     * Catat 1 kegagalan kredensial pada L1, L2, L3.
     */
    public function recordFailure(string $identifier, string $ip): void
    {
        $this->safely(fn () => $this->limiter->hit($this->key('l1', $identifier, $ip), (int) config('security.layers.identifier_ip.decay_seconds')));
        $this->safely(fn () => $this->limiter->hit($this->key('l2', $ip), (int) config('security.layers.ip.decay_seconds')));
        $this->safely(fn () => $this->limiter->hit($this->key('l3', $identifier), (int) config('security.layers.identifier.decay_seconds')));
    }

    public function assertTwoFactorNotLocked(string $identifier): void
    {
        $key = $this->key('2fa', $identifier);
        $max = (int) config('security.layers.two_factor.max_attempts');

        if ($this->safely(fn () => $this->limiter->tooManyAttempts($key, $max), false)) {
            $seconds = $this->safely(fn () => $this->limiter->availableIn($key), 60);

            throw ValidationException::withMessages([
                'code' => "Terlalu banyak kode salah. Coba lagi dalam {$seconds} detik.",
            ])->status(429);
        }
    }

    public function recordFailedTwoFactor(string $identifier): void
    {
        $this->safely(fn () => $this->limiter->hit($this->key('2fa', $identifier), (int) config('security.layers.two_factor.decay_seconds')));
    }

    /**
     * Login sukses penuh -> reset L1 & L3 + 2FA untuk identifier (L2 tidak direset).
     */
    public function clear(string $identifier, string $ip): void
    {
        $this->safely(fn () => $this->limiter->clear($this->key('l1', $identifier, $ip)));
        $this->safely(fn () => $this->limiter->clear($this->key('l3', $identifier)));
        $this->safely(fn () => $this->limiter->clear($this->key('2fa', $identifier)));
    }

    /**
     * Mitigasi user-enumeration via timing: samakan biaya saat user tidak ditemukan.
     */
    public function equalizeTiming(): void
    {
        Hash::check('dummy-password', '$2y$12$........................................................');
    }

    private function key(string $prefix, string ...$parts): string
    {
        return 'login:'.$prefix.':'.sha1(mb_strtolower(implode('|', $parts)));
    }

    /**
     * Bungkus operasi limiter — fail-open bila store bermasalah.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safely(callable $callback, mixed $fallback = null): mixed
    {
        if (! config('security.fail_open', true)) {
            return $callback();
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('LoginThrottle: operasi store gagal, fail-open. '.$e->getMessage());

            return $fallback;
        }
    }
}
