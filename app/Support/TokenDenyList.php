<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Hybrid revocation deny-list (ADR-0003, epics/sprint-1-plan.md §5.4).
 *
 * Hanya relevan untuk token dengan `active_role` sensitif — role umum tetap stateless.
 * Store = Redis (`security.store`); fail-open bila store tidak bisa dihubungi.
 */
class TokenDenyList
{
    private Repository $store;

    public function __construct()
    {
        $this->store = Cache::store(config('security.store', 'throttle'));
    }

    /**
     * Cabut satu access token spesifik (berdasarkan jti). TTL = sisa umur token.
     */
    public function revokeToken(string $jti, DateTimeInterface $expiresAt): void
    {
        $ttl = max(1, $expiresAt->getTimestamp() - now()->getTimestamp());

        $this->safely(fn () => $this->store->put($this->jtiKey($jti), 1, $ttl));
    }

    /**
     * Cabut SEMUA token milik user: token yang diterbitkan (iat) sebelum
     * stempel ini akan ditolak. TTL = umur refresh token maksimum.
     */
    public function revokeAllForUser(int|string $userId): void
    {
        $ttl = (int) config('passport.ttl.refresh', 60 * 24 * 14) * 60;

        $this->safely(fn () => $this->store->put($this->userKey($userId), now()->getTimestamp(), $ttl));
    }

    public function isTokenRevoked(string $jti): bool
    {
        return (bool) $this->safely(fn () => $this->store->get($this->jtiKey($jti)), false);
    }

    public function isUserRevokedSince(int|string $userId, int $issuedAt): bool
    {
        $stamp = $this->safely(fn () => $this->store->get($this->userKey($userId)));

        return $stamp !== null && $issuedAt < (int) $stamp;
    }

    public function clearUser(int|string $userId): void
    {
        $this->safely(fn () => $this->store->forget($this->userKey($userId)));
    }

    private function jtiKey(string $jti): string
    {
        return 'revoke:jti:'.$jti;
    }

    private function userKey(int|string $userId): string
    {
        return 'revoke:user:'.$userId;
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safely(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('TokenDenyList: operasi store gagal. '.$e->getMessage());

            return $fallback;
        }
    }
}
