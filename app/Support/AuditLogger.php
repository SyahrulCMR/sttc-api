<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * Penulis audit log keamanan (write-side). Tidak pernah melempar — kegagalan
 * logging tidak boleh menggagalkan aksi utama.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        AuditEvent $event,
        ?User $user = null,
        ?string $identifier = null,
        array $context = [],
    ): void {
        try {
            LoginActivity::create([
                'user_id' => $user?->getKey(),
                'identifier' => $identifier ?? $user?->identifier,
                'event' => $event,
                'ip' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 1000) ?: null,
                'context' => $context ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
