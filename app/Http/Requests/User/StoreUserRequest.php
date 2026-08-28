<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255', 'unique:users,identifier'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            // TODO 1a-4: pindah ke assignment role via pivot role_user.
            'role' => ['required', 'string', 'in:mahasiswa,dosen,admin'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
        ];
    }
}
