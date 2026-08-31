<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logout untuk sesi web sttc-api sendiri (pasangan dari `/login` alur OAuth).
 *
 * Alur back-channel SLO dari resource server tetap lewat `POST /api/sso/logout`
 * (`SsoBackChannelController::broadcastLogout`) — controller ini hanya mengakhiri
 * sesi browser di sttc-api, tidak mengubah alur token/webhook yang sudah ada.
 */
class OAuthLogoutController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $this->audit->record(AuditEvent::Logout, $user, context: ['channel' => 'web']);
        }

        return redirect()->route('login');
    }
}
