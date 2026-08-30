<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 task 2b-1 — hapus alur opaque token lama.
 *
 * Tabel `sso_tokens` dipakai `SsoAuthController` (opaque token single-use) yang sudah
 * dihapus. Verifikasi token kini lokal via JWKS di resource server (adr/0003).
 *
 * `down()` membuat ulang skema identik dengan definisi asli di
 * `0001_01_01_000000_create_users_table.php` agar migrasi tetap reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sso_tokens');
    }

    public function down(): void
    {
        Schema::create('sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app'); // siakad|lms|blog
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
        });
    }
};
