<?php

use App\Actions\Auth\LoginThrottle;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

function failLogin(object $test, string $identifier = 'NIM01'): TestResponse
{
    return $test->post('/sso/login', [
        'identifier' => $identifier,
        'password' => 'definitely-wrong',
        'app' => 'siakad',
    ]);
}

it('locks the SSO login form after the L1 (identifier + IP) threshold', function () {
    User::factory()->create(['identifier' => 'NIM01', 'password' => bcrypt('Correct1!')]);

    foreach (range(1, 5) as $ignored) {
        failLogin($this)->assertSessionHasErrors('identifier');
    }

    // Kredensial benar pun ditolak karena sudah terkunci.
    $this->post('/sso/login', ['identifier' => 'NIM01', 'password' => 'Correct1!', 'app' => 'siakad'])
        ->assertSessionHasErrors('identifier');

    expect(session('errors')->first('identifier'))->toContain('Coba lagi dalam');
});

it('resets L1 for the identifier after a fully successful login', function () {
    User::factory()->create(['identifier' => 'NIM02', 'password' => bcrypt('Correct1!')]);

    foreach (range(1, 4) as $ignored) {
        failLogin($this, 'NIM02')->assertSessionHasErrors('identifier');
    }

    $this->post('/sso/login', ['identifier' => 'NIM02', 'password' => 'Correct1!', 'app' => 'siakad'])
        ->assertRedirect();

    // Counter direset -> 4 kegagalan lagi masih belum mengunci.
    foreach (range(1, 4) as $ignored) {
        failLogin($this, 'NIM02')->assertSessionHasErrors('identifier');
    }
    expect(session('errors')->first('identifier'))->not->toContain('Coba lagi dalam');
});

it('locks L2 by IP across many different identifiers', function () {
    $throttle = new LoginThrottle;

    foreach (range(1, 20) as $i) {
        $throttle->recordFailure("user{$i}", '9.9.9.9');
    }

    expect(fn () => $throttle->assertNotLocked('brand-new-user', '9.9.9.9'))
        ->toThrow(ValidationException::class);

    // IP lain tidak terpengaruh (tidak melempar).
    $throttle->assertNotLocked('brand-new-user', '8.8.8.8');
    expect(true)->toBeTrue();
});

it('locks L3 by identifier across many different IPs', function () {
    $throttle = new LoginThrottle;

    foreach (range(1, 10) as $i) {
        $throttle->recordFailure('victim', "10.0.0.{$i}");
    }

    expect(fn () => $throttle->assertNotLocked('victim', '10.0.0.250'))
        ->toThrow(ValidationException::class);
});

it('locks and reports the 2FA layer separately', function () {
    $throttle = new LoginThrottle;

    foreach (range(1, 5) as $ignored) {
        $throttle->recordFailedTwoFactor('u1');
    }

    expect(fn () => $throttle->assertTwoFactorNotLocked('u1'))->toThrow(ValidationException::class)
        // identifier lain tidak terpengaruh (tidak melempar)
        ->and(fn () => $throttle->assertTwoFactorNotLocked('u2'))->not->toThrow(ValidationException::class);
});
