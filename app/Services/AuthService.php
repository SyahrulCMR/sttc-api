<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserService $userService,
    ) {}

    public function register(array $data): array
    {
        $user = $this->userService->create($data);

        return [
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    /**
     * @throws AuthenticationException
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return [
            'user' => $user,
            'token' => $this->issueToken($user, $deviceName),
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }
    }

    private function issueToken(User $user, ?string $deviceName = null): string
    {
        return $user->createToken($deviceName ?? 'api-token')->plainTextToken;
    }
}
