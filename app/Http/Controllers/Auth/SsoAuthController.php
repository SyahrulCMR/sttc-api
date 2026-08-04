<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SsoSession;
use App\Models\SsoToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SsoAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $app = $request->query('app');
        abort_unless(array_key_exists($app, config('sso.apps')), 400, 'Aplikasi tidak dikenal');

        session(['sso_app' => $app]);

        return view('auth.sso-login', ['app' => $app]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
            'app'        => 'required|string',
        ]);

        $throttleKey = 'login:' . $request->identifier . '|' . $request->ip();

        // proteksi brute-force, pesan jelas kalau kena limit
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $user = User::where('identifier', $request->identifier)->first();

        // Kasus 1: akun tidak ditemukan / password salah
        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60); // lock progresif 60 detik per percobaan gagal

            throw ValidationException::withMessages([
                'identifier' => 'NIM/NIDN atau password yang Anda masukkan salah.',
            ]);
        }

        // Kasus 2: akun ditangguhkan
        if ($user->status === 'suspended') {
            throw ValidationException::withMessages([
                'identifier' => 'Akun Anda sedang ditangguhkan. Silakan hubungi administrator sistem.',
            ]);
        }

        // login sukses → reset rate limiter
        RateLimiter::clear($throttleKey);

        auth()->login($user);

        $app = $request->input('app', session('sso_app'));
        abort_unless($app, 400, 'Sesi aplikasi tidak ditemukan');

        $token = SsoToken::create([
            'token' => Str::random(64),
            'user_id' => $user->id,
            'app' => $app,
            'expires_at' => now()->addSeconds(config('sso.token_ttl')),
        ]);

        return redirect(config("sso.apps.$app.redirect_url") . '?token=' . $token->token);
    }

    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'app' => 'required|string',
            'secret' => 'required|string',
        ]);

        $expectedSecret = config("sso.apps.{$request->app}.secret");
        abort_unless(hash_equals($expectedSecret ?? '', $request->secret), 403, 'Secret tidak valid');

        $ssoToken = SsoToken::where('token', $request->token)
            ->where('app', $request->app)
            ->first();

        if (! $ssoToken || ! $ssoToken->isValid()) {
            return response()->json(['valid' => false, 'message' => 'Token invalid/expired'], 401);
        }

        $user = $ssoToken->user;

        if (! $user) {
            return response()->json(['valid' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        // Step 8: tandai token sudah dipakai (one-time use), kirim data profil
        $ssoToken->update(['is_used' => true]);

        return response()->json([
            'valid' => true,
            'user' => [
                'identifier' => $user->identifier,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function registerSession(Request $request)
    {
        $request->validate([
            'app' => 'required|string',
            'secret' => 'required|string',
            'user_identifier' => 'required|string',
            'local_session_id' => 'required|string',
        ]);

        $expectedSecret = config("sso.apps.{$request->app}.secret");
        abort_unless(hash_equals($expectedSecret ?? '', $request->secret), 403);

        $user = User::where('identifier', $request->user_identifier)->firstOrFail();

        SsoSession::updateOrCreate(
            ['app' => $request->app, 'local_session_id' => $request->local_session_id],
            ['user_id' => $user->id, 'last_seen_at' => now()]
        );

        return response()->json(['registered' => true]);
    }

    // dipanggil client app saat user klik logout (AC 3)
    public function broadcastLogout(Request $request)
    {
        $request->validate([
            'app' => 'required|string',
            'secret' => 'required|string',
            'user_identifier' => 'required|string',
        ]);

        $expectedSecret = config("sso.apps.{$request->app}.secret");
        abort_unless(hash_equals($expectedSecret ?? '', $request->secret), 403);

        $user = User::where('identifier', $request->user_identifier)->firstOrFail();

        // logout juga sesi lokal di SSO server sendiri
        if (auth()->id() === $user->id) {
            auth()->logout();
        }

        $sessions = SsoSession::where('user_id', $user->id)->get();

        foreach ($sessions as $session) {
            $webhook = config("sso.apps.{$session->app}.logout_webhook");
            $secret = config("sso.apps.{$session->app}.secret");

            try {
                Http::timeout(5)->asForm()->post($webhook, [
                    'secret' => $secret,
                    'local_session_id' => $session->local_session_id,
                ]);
            } catch (\Throwable $e) {
                // jangan gagalkan seluruh proses logout hanya karena 1 app down
                Log::warning("SLO gagal broadcast ke {$session->app}: {$e->getMessage()}");
            }
        }

        SsoSession::where('user_id', $user->id)->delete();

        return response()->json(['logged_out' => true]);
    }
}
