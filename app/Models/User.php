<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[ObservedBy(UserObserver::class)]
#[Fillable(['name', 'email', 'password', 'identifier', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'break_glass_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(RoleEnum|string $role): bool
    {
        $slug = $role instanceof RoleEnum ? $role->value : $role;

        return $this->roles->contains('slug', $slug);
    }

    /**
     * @param  iterable<RoleEnum|string>  $roles
     */
    public function hasAnyRole(iterable $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(RoleEnum|string $role): void
    {
        $slug = $role instanceof RoleEnum ? $role->value : $role;
        $model = Role::query()->where('slug', $slug)->firstOrFail();

        $this->roles()->syncWithoutDetaching($model);
        $this->unsetRelation('roles');
    }

    public function removeRole(RoleEnum|string $role): void
    {
        $slug = $role instanceof RoleEnum ? $role->value : $role;
        $model = Role::query()->where('slug', $slug)->first();

        if ($model !== null) {
            $this->roles()->detach($model);
            $this->unsetRelation('roles');
        }
    }

    /**
     * User dengan role sensitif wajib 2FA (step-up sebelum authorization code).
     */
    public function twoFactorRequired(): bool
    {
        return $this->hasAnyRole(RoleEnum::sensitive());
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
