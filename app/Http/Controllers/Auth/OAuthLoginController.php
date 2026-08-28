<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginThrottle;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OAuthLoginRequest;
use App\Models\User;
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
    public function __construct(private readonly LoginThrottle $throttle) {}

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

            throw ValidationException::withMessages([
                'identifier' => 'NIM/NIDN atau kata sandi yang Anda masukkan salah.',
            ]);
        }

        $this->assertAccountUsable($user);

        // --- SEAM 2FA (task 1b-1) -----------------------------------------------
        // Titik tunggal: kredensial valid, sebelum sesi login dibuat & authorization
        // code diterbitkan. 1b-1 menyisipkan step-up TOTP untuk role sensitif di sini.
        $this->challengeTwoFactorIfRequired($user, $request);
        // ----------------------------------------------------------------------

        $this->throttle->clear($identifier, $ip);

        Auth::login($user, remember: false);
        $request->session()->regenerate();

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
     * Stub untuk Sprint 1a — diisi pada 1b-1 (step-up TOTP role sensitif).
     */
    private function challengeTwoFactorIfRequired(User $user, Request $request): void
    {
        // no-op (1a)
    }
}
