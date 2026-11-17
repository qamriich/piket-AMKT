<?php

namespace Database\Seeders;

use App\Models\Geng;
use App\Services\RotasiGeng;
use Illuminate\Database\Seeder;

class GengSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= RotasiGeng::JUMLAH_GENG; $i++) {
            Geng::query()->updateOrCreate(['urutan' => $i], ['nama' => "Geng {$i}"]);
        }
    }
}
