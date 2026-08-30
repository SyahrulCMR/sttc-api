<?php

namespace App\Passport;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TokenDenyList;
use DateInterval;
use DateTimeImmutable;
use Laravel\Passport\Bridge\RefreshTokenRepository as BaseRefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * Refresh token TTL per-role (rencana Sprint 1 §5.1, Sprint 2 task 2c-1).
 *
 * Passport menetapkan TTL refresh secara global per-grant lewat
 * Passport::refreshTokensExpireIn(). Kelas ini memangkas TTL menjadi
 * `passport.ttl.refresh_sensitive` (default 8 jam) untuk token yang membawa
 * scope role sensitif (`role:super-admin` / `role:admin-keuangan`), tanpa
 * menyentuh league/Passport internal — cukup meng-override expiry entity
 * sebelum di-persist.
 *
 * Rotasi (revoke-on-use) sudah menjadi perilaku default league/oauth2-server v9.
 * Tambahan (task 2c-2): bila refresh token yang SUDAH dicabut dipakai lagi
 * (indikasi kebocoran / replay), seluruh token user masuk deny-list + audit.
 */
class RefreshTokenRepository extends BaseRefreshTokenRepository
{
    public function isRefreshTokenRevoked($tokenId): bool
    {
        $revoked = parent::isRefreshTokenRevoked($tokenId);

        if ($revoked) {
            $this->reportReuse((string) $tokenId);
        }

        return $revoked;
    }

    /**
     * Refresh token yang sudah mati dipakai lagi → perlakukan sebagai kompromi:
     * cabut semua token user + catat audit. Idempoten; tak pernah melempar.
     */
    private function reportReuse(string $tokenId): void
    {
        try {
            $accessTokenId = Passport::refreshToken()->newQuery()->whereKey($tokenId)->value('access_token_id');
            $userId = $accessTokenId
                ? Passport::token()->newQuery()->whereKey($accessTokenId)->value('user_id')
                : null;

            if ($userId === null) {
                return;
            }

            app(TokenDenyList::class)->revokeAllForUser($userId);
            app(AuditLogger::class)->record(
                AuditEvent::TokenRevoked,
                User::find($userId),
                context: ['reason' => 'refresh_reuse', 'refresh_token_id' => $tokenId],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        if ($this->carriesSensitiveRole($refreshTokenEntity->getAccessToken())) {
            $minutes = (int) config('passport.ttl.refresh_sensitive', 8 * 60);

            $refreshTokenEntity->setExpiryDateTime(
                (new DateTimeImmutable)->add(new DateInterval('PT'.max($minutes, 1).'M'))
            );
        }

        parent::persistNewRefreshToken($refreshTokenEntity);
    }

    private function carriesSensitiveRole(AccessTokenEntityInterface $accessToken): bool
    {
        $sensitive = array_map(
            static fn (Role $role): string => 'role:'.$role->value,
            Role::sensitive(),
        );

        foreach ($accessToken->getScopes() as $scope) {
            if (in_array($scope->getIdentifier(), $sensitive, true)) {
                return true;
            }
        }

        return false;
    }
}
