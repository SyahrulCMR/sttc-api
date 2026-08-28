<?php

namespace Database\Factories;

use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => (string) fake()->unique()->numerify('##########'),
            'role' => 'mahasiswa',
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Inactive,
        ]);
    }

    /**
     * Lampirkan satu atau lebih role (via pivot role_user).
     */
    public function withRole(RoleEnum|string ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            $ids = collect($roles)->map(function (RoleEnum|string $role) {
                $slug = $role instanceof RoleEnum ? $role->value : $role;

                return Role::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => RoleEnum::from($slug)->label(), 'description' => RoleEnum::from($slug)->description()],
                )->id;
            });

            $user->roles()->syncWithoutDetaching($ids->all());
        });
    }
}
