<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pemilihan & perpindahan konteks role (multi-role).
 *
 *  - GET/POST /select-role : picker sesudah login bila user punya >1 role
 *  - GET      /switch-role : perpindahan konteks TANPA login ulang (silent re-authorize)
 */
class RoleContextController extends Controller
{
    public function select(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $roles = $user->roles()->orderBy('name')->get();

        if ($roles->count() <= 1) {
            return redirect()->to($this->safeReturn($request->session()->pull('role_picker_return')));
        }

        return view('auth.role-picker', ['roles' => $roles]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'string']]);

        abort_unless($request->user()->hasRole($data['role']), 403);

        $request->session()->put('active_role', $data['role']);

        return redirect()->to($this->safeReturn($request->session()->pull('role_picker_return')));
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string'],
            'redirect' => ['required', 'string'],
        ]);

        abort_unless($request->user()->hasRole($data['role']), 403);

        $request->session()->put('active_role', $data['role']);

        return redirect()->to($this->safeReturn($data['redirect']));
    }

    /**
     * Hanya izinkan kembali ke endpoint authorize milik sttc-api sendiri.
     */
    private function safeReturn(?string $url): string
    {
        return $url && str_starts_with($url, url('/oauth/authorize')) ? $url : '/';
    }
}
