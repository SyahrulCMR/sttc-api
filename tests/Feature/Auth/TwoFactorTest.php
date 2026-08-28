<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\TwoFactorService;
use PragmaRX\Google2FA\Google2FA;

function otp(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

function sensitiveUser(bool $enrolled = false): User
{
    $user = User::factory()->withRole(Role::SuperAdmin)->create([
        'identifier' => 'SA-'.fake()->unique()->numerify('#####'),
        'password' => bcrypt('Rahasia1!'),
    ]);

    if ($enrolled) {
        app(TwoFactorService::class)->confirm($user, app(TwoFactorService::class)->generateSecret());
    }

    return $user->fresh();
}

it('lets a non-sensitive user log in without 2FA', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1', 'password' => bcrypt('Rahasia1!')]);

    $this->post('/login', ['identifier' => 'D1', 'password' => 'Rahasia1!'])
        ->assertRedirect();

    expect(auth()->check())->toBeTrue();
});

it('forces enrollment for a sensitive user who has not set up 2FA', function () {
    sensitiveUser();

    $this->post('/login', ['identifier' => User::first()->identifier, 'password' => 'Rahasia1!'])
        ->assertRedirect(route('two-factor.enroll'));

    expect(auth()->check())->toBeFalse()
        ->and(session('pending_2fa_user_id'))->not->toBeNull();
});

it('completes enrollment, shows recovery codes, then logs in', function () {
    $user = sensitiveUser();
    $this->post('/login', ['identifier' => $user->identifier, 'password' => 'Rahasia1!']);

    $this->get('/two-factor/enroll')->assertOk()->assertSee('QR code 2FA');
    $secret = session('2fa_enroll_secret');
    expect($secret)->toBeString();

    $this->post('/two-factor/enroll', ['code' => otp($secret)])
        ->assertRedirect(route('two-factor.recovery-codes'));

    expect(auth()->check())->toBeTrue()
        ->and($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->get('/two-factor/recovery-codes')->assertOk()->assertSee('Kode Pemulihan');
});

it('challenges an enrolled sensitive user and accepts a valid code', function () {
    $user = sensitiveUser(enrolled: true);

    $this->post('/login', ['identifier' => $user->identifier, 'password' => 'Rahasia1!'])
        ->assertRedirect(route('two-factor.challenge'));
    expect(auth()->check())->toBeFalse();

    $this->post('/two-factor/challenge', ['code' => otp((string) $user->two_factor_secret)])
        ->assertRedirect();

    expect(auth()->check())->toBeTrue();
});

it('rejects a wrong 2FA code and locks after 5 attempts', function () {
    $user = sensitiveUser(enrolled: true);
    $this->post('/login', ['identifier' => $user->identifier, 'password' => 'Rahasia1!']);

    foreach (range(1, 5) as $ignored) {
        $this->from(route('two-factor.challenge'))->followingRedirects()
            ->post('/two-factor/challenge', ['code' => '000000'])
            ->assertSee('Kode 2FA tidak valid.');
    }

    $this->from(route('two-factor.challenge'))->followingRedirects()
        ->post('/two-factor/challenge', ['code' => otp((string) $user->two_factor_secret)])
        ->assertSee('Coba lagi dalam');
});

it('accepts a single-use recovery code', function () {
    $user = sensitiveUser();
    $codes = app(TwoFactorService::class)->confirm($user, app(TwoFactorService::class)->generateSecret());
    $user->refresh();

    $this->post('/login', ['identifier' => $user->identifier, 'password' => 'Rahasia1!']);

    $this->post('/two-factor/challenge', ['recovery_code' => $codes[0]])->assertRedirect();
    expect(auth()->check())->toBeTrue()
        ->and($user->fresh()->two_factor_recovery_codes)->toHaveCount(7);
});

it('carries a sensitive user through 2FA and then issues an authorization code', function () {
    $user = sensitiveUser(enrolled: true);
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();
    $authorizeUrl = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    $this->get($authorizeUrl)->assertRedirect('/login');
    $this->post('/login', ['identifier' => $user->identifier, 'password' => 'Rahasia1!'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->post('/two-factor/challenge', ['code' => otp((string) $user->two_factor_secret)]);

    parse_str((string) parse_url((string) $this->get($authorizeUrl)->headers->get('Location'), PHP_URL_QUERY), $params);
    expect($params)->toHaveKey('code');
});
