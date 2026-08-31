<?php

namespace Database\Factories;

use App\Models\SsoSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SsoSession>
 */
class SsoSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = SsoSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'app' => fake()->randomElement(['sttc-siakad', 'sttc-website']),
            'local_session_id' => Str::random(40),
            'last_seen_at' => now(),
        ];
    }

    /** State: tentukan aplikasi spesifik */
    public function app(string $app): static
    {
        return $this->state(fn () => ['app' => $app]);
    }

    /** State: tentukan pemilik sesi */
    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /** State: sesi basi/kadaluarsa (untuk testing cleanup) */
    public function stale(): static
    {
        return $this->state(fn () => ['last_seen_at' => now()->subDays(2)]);
    }
}
