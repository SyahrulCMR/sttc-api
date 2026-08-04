<?php

// app/Models/SsoSession.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SsoSession extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi mass-assignment.
     */
    protected $fillable = [
        'user_id',
        'app',
        'local_session_id',
        'last_seen_at',
    ];

    /**
     * Casting tipe data.
     */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Relasi ke pemilik sesi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: filter sesi berdasarkan aplikasi tertentu (siakad|lms|blog).
     */
    public function scopeApp($query, string $app)
    {
        return $query->where('app', $app);
    }

    /**
     * Scope: filter seluruh sesi milik satu user (dipakai saat broadcast logout).
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Helper: update waktu aktivitas terakhir sesi ini.
     */
    public function touchLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
