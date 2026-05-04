<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        /** @var User $user */
        $user = parent::create($data);

        return $user;
    }

    public function update(int|string $id, array $data): User
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        /** @var User $user */
        $user = parent::update($id, $data);

        return $user;
    }
}
