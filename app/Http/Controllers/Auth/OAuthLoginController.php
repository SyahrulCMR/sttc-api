<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginThrottle;
use App\Enums\AuditEvent;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OAuthLoginRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login untuk alur OAuth2 Authorization Code (`/oauth/authorize`).
 *
 * Terpisah dari SsoAuthController (SSO opaque lama) yang masih koeksistensi.
 */
class OAuthLoginController extends Controller
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly AuditLogger $audit,
    ) {}

    public function show(): View
    {
        return view('auth.oauth-login');
    }

    public function store(OAuthLoginRequest $request): RedirectResponse
    {
        $identifier = (string) $request->string('identifier');
        $ip = $request->ip();

        $this->throttle->assertNotLocked($identifier, $ip);

        $user = User::query()->where('identifier', $identifier)->first();

        if (! $user || ! Hash::check((string) $request->string('password'), $user->password)) {
            if (! $user) {
                $this->throttle->equalizeTiming();
            }

            $this->throttle->recordFailure($identifier, $ip);
            $this->audit->record(AuditEvent::LoginFailed, $user, $identifier);

            throw ValidationException::withMessages([
                'identifier' => 'NIM/NIDN atau kata sandi yang Anda masukkan salah.',
            ]);
        }

        $this->assertAccountUsable($user);

        // --- SEAM 2FA: kredensial valid, sebelum sesi login & authorization code ---
        if ($redirect = $this->challengeTwoFactorIfRequired($user, $request)) {
            $this->audit->record(AuditEvent::TwoFactorChallenged, $user);

            return $redirect;
        }

        $this->throttle->clear($identifier, $ip);

        Auth::login($user, remember: false);
        $request->session()->regenerate();
        $this->audit->record(AuditEvent::LoginSuccess, $user);

        return redirect()->intended('/');
    }

    private function assertAccountUsable(User $user): void
    {
        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun Anda sedang ditangguhkan. Silakan hubungi administrator sistem.',
            ]);
        }

        if ($user->status === UserStatus::Inactive) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun Anda sudah tidak aktif. Silakan hubungi administrator sistem.',
            ]);
        }
    }

    /**
     * Step-up TOTP untuk role sensitif. Mengembalikan redirect ke enrollment
     * (bila belum terdaftar) atau ke challenge; null bila 2FA tidak diperlukan.
     */
    private function challengeTwoFactorIfRequired(User $user, Request $request): ?RedirectResponse
    {
        if (! $user->twoFactorRequired()) {
            return null;
        }

        $request->session()->put('pending_2fa_user_id', $user->id);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.enroll');
        }

        $this->throttle->assertTwoFactorNotLocked($user->identifier);

        return redirect()->route('two-factor.challenge');
    }
}
