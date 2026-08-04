<?php

namespace Database\Seeders;

use App\Models\SsoSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SsoSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mahasiswa = User::firstOrCreate(
            ['identifier' => '2201234567'],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@sttcipasung.ac.id',
                'password' => Hash::make('password123'),
                'role' => 'mahasiswa',
                'status' => 'active',
            ]
        );

        foreach (['siakad', 'lms', 'blog'] as $app) {
            SsoSession::factory()
                ->app($app)
                ->forUser($mahasiswa)
                ->create();
        }

        // User demo berstatus suspended (untuk manual test AC 4)
        User::firstOrCreate(
            ['identifier' => '2201234999'],
            [
                'name' => 'Dosen Ditangguhkan',
                'email' => 'suspended@sttcipasung.ac.id',
                'password' => Hash::make('password123'),
                'role' => 'dosen',
                'status' => 'suspended',
            ]
        );

        $this->command->info('Seed selesai: Budi Santoso punya 3 sesi aktif (siakad/lms/blog). Akun suspended tersedia untuk test AC4.');
    }
}
