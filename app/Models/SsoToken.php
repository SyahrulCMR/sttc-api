<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SsoToken extends Model
{
    protected $fillable = ['token', 'user_id', 'app', 'expires_at', 'is_used'];

    protected $casts = ['expires_at' => 'datetime', 'is_used' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return ! $this->is_used && $this->expires_at->isFuture();
    }
}
