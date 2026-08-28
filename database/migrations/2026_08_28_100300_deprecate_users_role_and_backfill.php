<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only: isi pivot `role_user` dari kolom enum lama `users.role`.
 *
 * Kolom `users.role` sudah dijadikan nullable + di-deprecate di migration
 * 2026_08_28_100000. Kolom belum di-drop di Sprint 1 (alur SSO lama masih membacanya).
 */
return new class extends Migration
{
    /** Pemetaan nilai enum lama -> slug role baru. */
    private const MAP = [
        'mahasiswa' => 'mahasiswa',
        'dosen' => 'dosen',
        'admin' => 'super-admin',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $now = now();

        DB::table('users')->whereNotNull('role')->orderBy('id')
            ->each(function (object $user) use ($roleIds, $now) {
                $slug = self::MAP[$user->role] ?? null;

                if ($slug !== null && isset($roleIds[$slug])) {
                    DB::table('role_user')->insertOrIgnore([
                        'user_id' => $user->id,
                        'role_id' => $roleIds[$slug],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'slug');

        // Hapus tepat pasangan (user, role) yang ditambahkan oleh up().
        DB::table('users')->whereNotNull('role')->orderBy('id')
            ->each(function (object $user) use ($roleIds) {
                $slug = self::MAP[$user->role] ?? null;

                if ($slug !== null && isset($roleIds[$slug])) {
                    DB::table('role_user')
                        ->where('user_id', $user->id)
                        ->where('role_id', $roleIds[$slug])
                        ->delete();
                }
            });
    }
};
