<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Log aktivitas keamanan — APPEND-ONLY. Tidak boleh di-update / di-delete.
 * (Read/filter API = Sprint 2.)
 */
class LoginActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'identifier', 'event', 'ip', 'user_agent', 'context'];

    protected $casts = [
        'event' => AuditEvent::class,
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('LoginActivity bersifat append-only.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('LoginActivity bersifat append-only.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
