<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
        // Minggu 16 Nov 2026 = Geng 1, batas jam kuliah 09.00 (PRD bagian 3 dan 12).
        foreach (Pengaturan::DEFAULTS as $key => $value) {
            if (! Pengaturan::query()->whereKey($key)->exists()) {
                Pengaturan::simpan($key, $value);
            }
        }
    }
}
