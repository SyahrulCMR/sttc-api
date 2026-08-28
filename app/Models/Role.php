<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['slug', 'name', 'description'];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function toEnum(): ?RoleEnum
    {
        return RoleEnum::tryFrom($this->slug);
    }

    public function isSensitive(): bool
    {
        return (bool) $this->toEnum()?->isSensitive();
    }
}
