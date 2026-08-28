<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required_without:recovery_code', 'nullable', 'string'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required_without' => 'Masukkan kode 2FA atau kode pemulihan.',
            'recovery_code.required_without' => 'Masukkan kode 2FA atau kode pemulihan.',
        ];
    }
}
