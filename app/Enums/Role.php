<?php

namespace App\Enums;

/**
 * 10 role ekosistem STTC (PRD Bagian C.1 Tabel 0).
 *
 * Sumber kebenaran penetapan role adalah pivot `role_user`; enum ini adalah
 * daftar kanonis slug + metadata (label, sensitivitas untuk 2FA/deny-list).
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case AdminBaak = 'admin-baak';
    case AdminKeuangan = 'admin-keuangan';
    case Kaprodi = 'kaprodi';
    case Dosen = 'dosen';
    case Mahasiswa = 'mahasiswa';
    case AdminPmb = 'admin-pmb';
    case AdminLppm = 'admin-lppm';
    case AdminCms = 'admin-cms';
    case Pimpinan = 'pimpinan';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin (IT)',
            self::AdminBaak => 'Admin BAAK',
            self::AdminKeuangan => 'Admin Keuangan',
            self::Kaprodi => 'Kaprodi',
            self::Dosen => 'Dosen',
            self::Mahasiswa => 'Mahasiswa',
            self::AdminPmb => 'Admin PMB',
            self::AdminLppm => 'Admin LPPM',
            self::AdminCms => 'Admin CMS/Humas',
            self::Pimpinan => 'Pimpinan/Yayasan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Akses penuh sistem, manajemen role & konfigurasi IAM.',
            self::AdminBaak => 'Administrasi akademik & kemahasiswaan (BAAK).',
            self::AdminKeuangan => 'Administrasi keuangan mahasiswa & tagihan.',
            self::Kaprodi => 'Kepala Program Studi — kurikulum & RPS prodinya.',
            self::Dosen => 'Pengajar — RPS mata kuliah yang diampu, nilai.',
            self::Mahasiswa => 'Mahasiswa aktif — layanan akademik mandiri.',
            self::AdminPmb => 'Penerimaan Mahasiswa Baru.',
            self::AdminLppm => 'Lembaga Penelitian & Pengabdian Masyarakat.',
            self::AdminCms => 'Pengelola konten publik (website & humas).',
            self::Pimpinan => 'Pimpinan/Yayasan — dashboard & laporan (read-only).',
        };
    }

    /**
     * Role dengan akses data sensitif — wajib 2FA + hybrid revocation check.
     */
    public function isSensitive(): bool
    {
        return in_array($this, self::sensitive(), true);
    }

    /**
     * @return list<self>
     */
    public static function sensitive(): array
    {
        return [self::SuperAdmin, self::AdminKeuangan];
    }
}
