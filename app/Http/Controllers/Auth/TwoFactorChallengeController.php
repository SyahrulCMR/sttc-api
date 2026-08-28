<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginThrottle;
use App\Enums\AuditEvent;
use App\Http\Controllers\Auth\Concerns\ResolvesTwoFactorUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Services\TwoFactorService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Step-up TOTP: dipanggil setelah kredensial valid, sebelum sesi login dibuat
 * & authorization code diterbitkan (role sensitif — 1a-9 seam).
 */
class TwoFactorChallengeController extends Controller
{
    use ResolvesTwoFactorUser;

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly LoginThrottle $throttle,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pending_2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $this->twoFactorUser($request);

        $this->throttle->assertTwoFactorNotLocked($user->identifier);

        $passed = $request->filled('recovery_code')
            ? $this->twoFactor->useRecoveryCode($user, (string) $request->string('recovery_code'))
            : $this->twoFactor->verify((string) $user->two_factor_secret, (string) $request->string('code'));

        if (! $passed) {
            $this->throttle->recordFailedTwoFactor($user->identifier);
            $this->audit->record(AuditEvent::TwoFactorFailed, $user);

            throw ValidationException::withMessages([
                'code' => 'Kode 2FA tidak valid.',
            ]);
        }

        $this->throttle->clear($user->identifier, $request->ip());
        $request->session()->forget('pending_2fa_user_id');

        Auth::login($user);
        $request->session()->regenerate();
        $this->audit->record(AuditEvent::LoginSuccess, $user, context: ['via' => 'two_factor']);

        return redirect()->intended('/');
    }
}
