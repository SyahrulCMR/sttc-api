<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\SsoSession;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TokenDenyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

/**
 * Kanal back-channel SSO server-to-server (dipanggil resource server, bukan browser).
 *
 * - `registerSession`  : RS mendaftarkan sesi lokalnya setelah callback OAuth berhasil,
 *   supaya bisa di-Single-Logout nanti.
 * - `broadcastLogout`  : RS memberi tahu saat user logout → cabut token OAuth + deny-list
 *   + audit, lalu broadcast webkook force-logout ke seluruh RS terdaftar.
 *
 * `SsoSession` + alur ini dipertahankan permanen (rencana Sprint 1 §11). Alur opaque
 * token lama (`/sso/login`, `/api/sso/verify`, model `SsoToken`) dihapus di Sprint 2 task 2b-1.
 * Konfigurasi per-app: `config/sso.php` (direkonsiliasi ke identitas client OAuth di task 2b-3).
 */
class SsoBackChannelController extends Controller
{
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

        // Akhiri SEMUA sesi web sttc-api milik user ini — bukan hanya sesi request
        // saat ini (panggilan back-channel ini server-to-server, tidak membawa cookie
        // browser user, jadi `auth()->id()` tidak akan pernah cocok di skenario nyata).
        // Pola sama dengan SsoWebhookController::forceLogout() di sisi resource server.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        // Cabut token OAuth (Passport) + deny-list (di atas token revocation Passport).
        $this->revokeOAuthTokens($user);
        app(TokenDenyList::class)->revokeAllForUser($user->id);
        app(AuditLogger::class)->record(AuditEvent::Logout, $user, context: ['channel' => 'back-channel', 'app' => $request->app]);

        // Webhook front-channel ke app LAIN. App pemicu tidak di-webhook: ia sudah
        // menutup sesinya sendiri, dan meng-webhook-nya balik = reentrancy (deadlock di
        // dev server single-worker). Sesi app-pemicu yang lain (mis. tab kedua) tetap
        // mati via kegagalan refresh (refresh token sudah dicabut di atas).
        $sessions = SsoSession::where('user_id', $user->id)
            ->where('app', '!=', $request->app)
            ->get();

        foreach ($sessions as $session) {
            $webhook = config("sso.apps.{$session->app}.logout_webhook");
            $secret = config("sso.apps.{$session->app}.secret");

            try {
                // `->throw()` wajib: Http::post() TIDAK melempar exception untuk
                // response 4xx/5xx (mis. webhook salah path → 404) tanpa ini, jadi
                // kegagalan lolos ke blok sukses dan tidak pernah tercatat di log.
                Http::timeout(5)->asForm()->throw()->post($webhook, [
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

    /**
     * Cabut seluruh access + refresh token Passport milik user.
     */
    private function revokeOAuthTokens(User $user): void
    {
        $tokenIds = Passport::token()->newQuery()
            ->where('user_id', $user->getKey())
            ->where('revoked', false)
            ->pluck('id');

        if ($tokenIds->isEmpty()) {
            return;
        }

        Passport::token()->newQuery()
            ->whereIn('id', $tokenIds)
            ->update(['revoked' => true]);

        Passport::refreshToken()->newQuery()
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);
    }
}
