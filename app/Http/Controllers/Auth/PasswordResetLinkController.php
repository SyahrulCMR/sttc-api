<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'identifier' => 'required|string',
        ], [
            'identifier.required' => 'NIM/NIDN atau Email wajib diisi.',
        ]);

        // Cari user di database SSO
        $user = User::where('identifier', $request->identifier)
            ->orWhere('email', $request->identifier)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'identifier' => 'NIM/NIDN atau Email tidak ditemukan dalam sistem SSO.',
            ]);
        }

        // Cek apakah user sudah memiliki pengajuan yang masih bernilai 'pending'
        $existingRequest = PasswordResetRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return back()->with('status', 'Pengajuan reset kata sandi Anda sebelumnya masih menunggu verifikasi dari Admin.');
        }

        // Simpan pengajuan baru
        PasswordResetRequest::create([
            'user_id' => $user->id,
            'identifier' => $user->identifier,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Pengajuan reset kata sandi berhasil dikirim. Silakan hubungi/tunggu verifikasi dari Administrator.');
    }
}
