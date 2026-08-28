<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client as PassportClient;

/**
 * Client OAuth kustom: client internal first-party (sttc-siakad, sttc-website)
 * melewati layar consent (ADR-0002 — 3 client internal diketahui sejak awal).
 */
class OAuthClient extends PassportClient
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->firstParty();
    }
}
