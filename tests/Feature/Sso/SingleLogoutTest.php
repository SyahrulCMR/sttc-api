<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\LoginActivity;
use App\Models\SsoSession;
use App\Models\User;
use App\Support\TokenDenyList;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    config([
        'sso.apps.siakad.secret' => 'siakad-secret',
        'sso.apps.siakad.logout_webhook' => 'https://siakad.test/api/sso/force-logout',
    ]);
    Http::fake();
});

it('rejects a wrong app secret', function () {
    $user = User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1']);

    $this->postJson('/api/sso/logout', [
        'app' => 'siakad',
        'secret' => 'salah',
        'user_identifier' => 'D1',
    ])->assertForbidden();
});

it('revokes tokens, deletes sso sessions, fires webhooks and audits', function () {
    $user = User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1']);
    $accessToken = issueAccessToken($this, $user, 'dosen');
    $issuedBefore = now()->subMinute()->getTimestamp();

    SsoSession::create(['user_id' => $user->id, 'app' => 'siakad', 'local_session_id' => 'sess-1', 'last_seen_at' => now()]);

    $this->postJson('/api/sso/logout', [
        'app' => 'siakad',
        'secret' => 'siakad-secret',
        'user_identifier' => 'D1',
    ])->assertOk()->assertJson(['logged_out' => true]);

    expect(SsoSession::where('user_id', $user->id)->count())->toBe(0)
        ->and(app(TokenDenyList::class)->isUserRevokedSince($user->id, $issuedBefore))->toBeTrue()
        ->and(LoginActivity::where('user_id', $user->id)->where('event', AuditEvent::Logout->value)->exists())->toBeTrue();

    // token OAuth Passport ditandai revoked
    $payload = decodeJwtPayload($accessToken);
    expect(Passport::token()->newQuery()->whereKey($payload['jti'])->value('revoked'))->toBeTruthy();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'siakad.test/api/sso/force-logout'));
});
