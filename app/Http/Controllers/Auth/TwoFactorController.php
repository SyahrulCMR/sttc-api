<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ResolvesTwoFactorUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorEnrollRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Enrollment 2FA. Wajib untuk role sensitif yang belum terdaftar (dipaksa di
 * login berikutnya, tanpa grace) — juga bisa self-service oleh user yang login.
 */
class TwoFactorController extends Controller
{
    use ResolvesTwoFactorUser;

    private const SESSION_SECRET = '2fa_enroll_secret';

    private const SESSION_CODES = '2fa_recovery_codes_display';

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->twoFactorUser($request);

        $secret = $request->session()->get(self::SESSION_SECRET) ?: $this->twoFactor->generateSecret();
        $request->session()->put(self::SESSION_SECRET, $secret);

        return view('auth.two-factor-enroll', [
            'secret' => $secret,
            'qr' => $this->twoFactor->qrCodeDataUri($user->identifier, $secret),
        ]);
    }

    public function store(TwoFactorEnrollRequest $request): RedirectResponse
    {
        $user = $this->twoFactorUser($request);
        $secret = (string) $request->session()->get(self::SESSION_SECRET);

        if ($secret === '' || ! $this->twoFactor->verify($secret, (string) $request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => 'Kode tidak cocok. Pastikan waktu perangkat Anda sinkron.',
            ]);
        }

        $codes = $this->twoFactor->confirm($user, $secret);

        $request->session()->forget(self::SESSION_SECRET);
        $request->session()->put(self::SESSION_CODES, $codes);

        if ($this->isLoginFlow($request)) {
            $request->session()->forget('pending_2fa_user_id');
            Auth::login($user);
            $request->session()->regenerate();
            // pindahkan daftar recovery code melewati session regenerate
            $request->session()->put(self::SESSION_CODES, $codes);
        }

        return redirect()->route('two-factor.recovery-codes');
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get(self::SESSION_CODES);

        if (! is_array($codes)) {
            return redirect('/');
        }

        return view('auth.two-factor-recovery-codes', ['codes' => $codes]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_CODES);

        return redirect()->intended('/');
    }
}
