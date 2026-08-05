<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetRequestController extends Controller
{
    /**
     * Tampilkan daftar pengajuan reset password (untuk Admin).
     */
    public function index()
    {
        $requests = PasswordResetRequest::with('user')
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->latest()
            ->paginate(15);

        return view('admin.password-requests.index', compact('requests'));
    }

    /**
     * Setujui pengajuan & Generate Password Sementara
     */
    public function approve(Request $request, PasswordResetRequest $resetRequest)
    {
        if ($resetRequest->status !== 'pending') {
            return back()->with('error', 'Pengajuan ini sudah diproses sebelumnya.');
        }

        // Generate password acak 8 karakter (contoh: SttC#8x2)
        $temporaryPassword = Str::random(8);

        // Update password user
        $user = $resetRequest->user;
        $user->update([
            'password' => Hash::make($temporaryPassword),
        ]);

        // Update status request
        $resetRequest->update([
            'status'     => 'approved',
            'action_by'  => auth()->id(),
            'admin_note' => $request->input('admin_note', 'Password sementara telah dibuat: ' . $temporaryPassword),
        ]);

        return back()->with('success', "Password untuk {$user->name} ({$user->identifier}) berhasil di-reset menjadi: {$temporaryPassword}");
    }

    /**
     * Tolak pengajuan reset password
     */
    public function reject(Request $request, PasswordResetRequest $resetRequest)
    {
        $request->validate(['admin_note' => 'required|string']);

        $resetRequest->update([
            'status'     => 'rejected',
            'action_by'  => auth()->id(),
            'admin_note' => $request->admin_note,
        ]);

        return back()->with('success', 'Pengajuan reset password berhasil ditolak.');
    }
}
