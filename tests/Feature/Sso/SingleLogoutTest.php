<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\LoginActivity;
use App\Models\SsoSession;
use App\Models\User;
use App\Support\TokenDenyList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

beforeEach(function () {
    config([
        'sso.apps.sttc-siakad.secret' => 'siakad-backchannel',
        'sso.apps.sttc-siakad.logout_webhook' => 'https://siakad.test/sso/force-logout',
        'sso.apps.sttc-website.secret' => 'website-backchannel',
        'sso.apps.sttc-website.logout_webhook' => 'https://website.test/sso/force-logout',
    ]);
});

it('resolves per-app back-channel config keyed by OAuth client id', function () {
    // Kunci config = client_id yang dikirim RS pada field `app`.
    expect(config('sso.apps'))->toHaveKeys(['sttc-siakad', 'sttc-website'])
        ->and(config('sso.apps.sttc-website.logout_webhook'))->toEndWith('/sso/force-logout');
});

it('rejects a wrong app secret', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1']);

    $this->postJson('/api/sso/logout', [
        'app' => 'sttc-siakad',
        'secret' => 'salah',
        'user_identifier' => 'D1',
    ])->assertForbidden();
});

it('rejects an unknown app', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1']);

    $this->postJson('/api/sso/logout', [
        'app' => 'not-a-client',
        'secret' => 'whatever',
        'user_identifier' => 'D1',
    ])->assertForbidden();
});

it('registers sessions then revokes tokens, force-logs-out OTHER apps and audits', function () {
    Http::fake();

    $user = User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1']);
    $accessToken = issueAccessToken($this, $user, 'dosen');
    $issuedBefore = now()->subMinute()->getTimestamp();

    // User login di dua app; masing-masing mendaftarkan sesinya (front-channel SLO).
    $this->postJson('/api/sso/register-session', [
        'app' => 'sttc-siakad', 'secret' => 'siakad-backchannel',
        'user_identifier' => 'D1', 'local_session_id' => 'siakad-sess',
    ])->assertOk();
    $this->postJson('/api/sso/register-session', [
        'app' => 'sttc-website', 'secret' => 'website-backchannel',
        'user_identifier' => 'D1', 'local_session_id' => 'website-sess',
    ])->assertOk();

    expect(SsoSession::where('user_id', $user->id)->count())->toBe(2);

    // Logout dipicu dari siakad.
    $this->postJson('/api/sso/logout', [
        'app' => 'sttc-siakad', 'secret' => 'siakad-backchannel', 'user_identifier' => 'D1',
    ])->assertOk()->assertJson(['logged_out' => true]);

    expect(SsoSession::where('user_id', $user->id)->count())->toBe(0)
        ->and(app(TokenDenyList::class)->isUserRevokedSince($user->id, $issuedBefore))->toBeTrue()
        ->and(LoginActivity::where('user_id', $user->id)->where('event', AuditEvent::Logout->value)->exists())->toBeTrue();

    $payload = decodeJwtPayload($accessToken);
    expect(Passport::token()->newQuery()->whereKey($payload['jti'])->value('revoked'))->toBeTruthy();

    // Webhook HANYA ke app lain (website), bukan balik ke pemicu (siakad).
    Http::assertSent(fn ($r) => str_contains($r->url(), 'website.test/sso/force-logout'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'siakad.test/sso/force-logout'));
});

it('destroys the users own sttc-api web sessions on back-channel logout (BUG-0001)', function () {
    Http::fake();

    $user = User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1']);
    $otherUser = User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D2']);

    DB::table('sessions')->insert([
        ['id' => 'web-sess-1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => now()->getTimestamp()],
        ['id' => 'web-sess-2', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => now()->getTimestamp()],
        ['id' => 'other-user', 'user_id' => $otherUser->id, 'payload' => 'x', 'last_activity' => now()->getTimestamp()],
    ]);

    $this->postJson('/api/sso/logout', [
        'app' => 'sttc-siakad', 'secret' => 'siakad-backchannel', 'user_identifier' => 'D1',
    ])->assertOk();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'other-user')->exists())->toBeTrue();
});

it('logs a warning when a force-logout webhook returns an HTTP error (BUG-0002)', function () {
    Http::fake([
        '*website.test/*' => Http::response('not found', 404),
        '*' => Http::response(),
    ]);
    Log::spy();

    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1']);
    $this->postJson('/api/sso/register-session', [
        'app' => 'sttc-website', 'secret' => 'website-backchannel',
        'user_identifier' => 'D1', 'local_session_id' => 'website-sess',
    ])->assertOk();

    // Logout dipicu dari siakad → webhook ke website (yang membalas 404).
    $this->postJson('/api/sso/logout', [
        'app' => 'sttc-siakad', 'secret' => 'siakad-backchannel', 'user_identifier' => 'D1',
    ])->assertOk()->assertJson(['logged_out' => true]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'SLO gagal broadcast ke sttc-website'))
        ->once();
});
