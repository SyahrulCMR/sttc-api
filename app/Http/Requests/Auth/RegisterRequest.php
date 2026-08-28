<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Catatan: endpoint self-service register dinonaktifkan sejak Sprint 1 (lihat routes/api.php).
 * Rules dipertahankan agar tetap valid bila provisioning internal memakainya kembali.
 */
class RegisterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255', 'unique:users,identifier'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', 'in:mahasiswa,dosen,admin'],
        ];
    }
}
