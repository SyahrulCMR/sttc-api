<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Masukkan kode 6 digit dari aplikasi authenticator Anda.',
        ];
    }
}
