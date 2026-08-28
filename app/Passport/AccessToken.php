<?php

namespace App\Passport;

use DateTimeImmutable;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * Access token entity dengan custom claim (ADR-0003).
 *
 * TIDAK meng-extend Laravel\Passport\Bridge\AccessToken: `convertToJWT()` di
 * AccessTokenTrait bersifat private sehingga tak bisa dioverride lewat pewarisan.
 * Kelas ini memakai trait league langsung lalu mendefinisikan convertToJWT() sendiri
 * (method milik kelas menang atas method trait) — dengan akses penuh ke properti
 * private $jwtConfiguration milik trait karena komposisi trait = satu scope kelas.
 *
 * Klaim tambahan: iss, roles, active_role, status.
 * (kid header + JWKS = task 1b-7)
 */
class AccessToken implements AccessTokenEntityInterface
{
    use AccessTokenTrait, EntityTrait, TokenEntityTrait;

    /**
     * @param  ScopeEntityInterface[]  $scopes
     */
    public function __construct(?string $userIdentifier, array $scopes, ClientEntityInterface $client)
    {
        if ($userIdentifier !== null) {
            $this->setUserIdentifier($userIdentifier);
        }

        foreach ($scopes as $scope) {
            $this->addScope($scope);
        }

        $this->setClient($client);
    }

    private function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        $claims = app(TokenClaims::class)->forUser($this->getUserIdentifier());
        $now = new DateTimeImmutable;

        return $this->jwtConfiguration->builder()
            ->issuedBy((string) config('app.url'))
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getSubjectIdentifier())
            ->withClaim('scopes', $this->getScopes())
            ->withClaim('roles', $claims['roles'])
            ->withClaim('active_role', $this->activeRoleFromScopes())
            ->withClaim('status', $claims['status'])
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }

    private function activeRoleFromScopes(): ?string
    {
        foreach ($this->getScopes() as $scope) {
            $identifier = $scope->getIdentifier();

            if (str_starts_with($identifier, 'role:')) {
                return substr($identifier, 5);
            }
        }

        return null;
    }
}
