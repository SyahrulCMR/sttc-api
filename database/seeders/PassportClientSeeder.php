<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Mendaftarkan 2 client OAuth internal (first-party, confidential, Auth Code + PKCE).
 *
 * LOKAL/DEV ONLY: id & secret bersifat deterministik supaya .env resource server
 * (sttc-siakad / sttc-website) tidak berubah tiap re-seed.
 *
 * STAGING/PROD: JANGAN jalankan seeder ini. Daftarkan client via:
 *   php artisan passport:client --name="sttc-siakad" --redirect_uri="https://siakad.stt-cipasung.ac.id/sso/callback"
 * lalu simpan client_id + secret ke secret manager. Lihat docs/oauth-clients.md.
 */
class PassportClientSeeder extends Seeder
{
    private const CLIENTS = [
        [
            'id' => '01999999-0000-7000-8000-0000000000a1',
            'name' => 'sttc-siakad',
            'secret_env' => 'SIAKAD_CLIENT_SECRET',
            'secret_default' => 'sttc-siakad-local-dev-secret',
            'redirect_env' => 'SIAKAD_REDIRECT_URI',
            'redirect_default' => 'http://localhost:8001/sso/callback',
        ],
        [
            'id' => '01999999-0000-7000-8000-0000000000a2',
            'name' => 'sttc-website',
            'secret_env' => 'WEBSITE_CLIENT_SECRET',
            'secret_default' => 'sttc-website-local-dev-secret',
            'redirect_env' => 'WEBSITE_REDIRECT_URI',
            'redirect_default' => 'http://localhost:8002/sso/callback',
        ],
        [
            'id' => '01999999-0000-7000-8000-0000000000a3',
            'name' => 'dev-playground',
            'secret_env' => 'DEV_PLAYGROUND_CLIENT_SECRET',
            'secret_default' => 'dev-playground-secret',
            'redirect_env' => 'DEV_PLAYGROUND_REDIRECT_URI',
            'redirect_default' => 'http://localhost:8000/dev/oauth/callback',
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('PassportClientSeeder dilewati di environment production.');

            return;
        }

        foreach (self::CLIENTS as $client) {
            $redirectUris = collect(explode(',', (string) env($client['redirect_env'], $client['redirect_default'])))
                ->map(fn (string $uri) => trim($uri))
                ->filter()
                ->values()
                ->all();

            DB::table('oauth_clients')->updateOrInsert(
                ['id' => $client['id']],
                [
                    'name' => $client['name'],
                    // Passport 13 selalu menyimpan secret ter-hash; RS memakai plaintext dari .env-nya.
                    'secret' => Hash::make((string) env($client['secret_env'], $client['secret_default'])),
                    'provider' => 'users',
                    'redirect_uris' => json_encode($redirectUris),
                    'grant_types' => json_encode(['authorization_code', 'refresh_token']),
                    'revoked' => false,
                    'owner_type' => null,
                    'owner_id' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $this->command?->info("Client OAuth '{$client['name']}' ({$client['id']}) siap. Redirect: ".implode(' ', $redirectUris));
        }
    }
}
