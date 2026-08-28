<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();

            // Konvensi Fortify — diisi pada Sprint 1b.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Prosedur break-glass Super Admin (Sprint 1b).
            $table->timestamp('break_glass_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
        });

        // status : active|suspended -> active|inactive|suspended
        // role   : NOT NULL -> nullable (DEPRECATED — digantikan pivot role_user di 1a-4;
        //          kolom dipertahankan untuk alur SSO lama / data historis)
        match (DB::getDriverName()) {
            'pgsql' => collect([
                'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check',
                "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive', 'suspended'))",
                'ALTER TABLE users ALTER COLUMN role DROP NOT NULL',
            ])->each(fn (string $sql) => DB::statement($sql)),
            // sqlite dsb.: enum() lama menaruh CHECK inline -> rebuild kolom jadi string biasa.
            default => Schema::table('users', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->change();
                $table->string('role')->nullable()->change();
            }),
        };

        // PENTING (SQLite): index ekspresi ini WAJIB dibuat setelah semua ->change() pada
        // tabel users. Rebuild tabel SQLite tidak bisa merekonstruksi index ekspresi —
        // migration berikutnya yang meng-alter users di SQLite harus drop lalu buat ulang.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
        }
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'suspended'))");

            DB::table('users')->whereNull('role')->update(['role' => 'mahasiswa']);
            DB::statement('ALTER TABLE users ALTER COLUMN role SET NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'break_glass_at',
                'password_changed_at',
            ]);
        });
    }
};
