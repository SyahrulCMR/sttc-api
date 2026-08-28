<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Login untuk alur OAuth authorize (Blade form). Sengaja meng-extend FormRequest
 * langsung (bukan BaseFormRequest yang berorientasi JSON) supaya kegagalan validasi
 * kembali ke form dengan error, bukan JSON 422.
 */
class OAuthLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'NIM/NIDN wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }
}
