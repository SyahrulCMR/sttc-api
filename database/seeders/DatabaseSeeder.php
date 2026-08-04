<?php

namespace Database\Seeders;

use Database\Seeders\SsoSessionSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
        SsoSessionSeeder::class,
    ]);
    }
}
