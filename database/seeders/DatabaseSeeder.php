<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GengSeeder::class,
            TugasSeeder::class,
            PengaturanSeeder::class,
            WargaContohSeeder::class,
        ]);
    }
}
