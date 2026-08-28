<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'identifier' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users', 'identifier')->ignore($userId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::defaults()],
            // TODO 1a-4: pindah ke assignment role via pivot role_user.
            'role' => ['sometimes', 'string', 'in:mahasiswa,dosen,admin'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
        ];
    }
}
