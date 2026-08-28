<?php

namespace App\Http\Requests\Role;

use App\Enums\Role;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(Role::class)],
        ];
    }

    public function role(): Role
    {
        return Role::from((string) $this->string('role'));
    }
}
