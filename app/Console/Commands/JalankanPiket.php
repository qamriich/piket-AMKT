<?php

namespace App\Console\Commands;

use App\Services\BintangOtomatis;
use App\Services\JadwalMinggu;
use App\Services\Pemberitahuan;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Tugas berkala (dijadwalkan tiap 15 menit di routes/console.php):
 * 1. menyiapkan minggu ini (penugasan harian dari jadwal periode, R1);
 * 2. bintang otomatis (B4);
 * 3. email piket harian minggu ini;
 * 4. pengingat (bagian 8).
 */
class JalankanPiket extends Command
{
    protected $signature = 'piket:jalankan';

    protected $description = 'Bintang otomatis, email piket harian, dan pengingat piket';

    public function handle(JadwalMinggu $jadwal, BintangOtomatis $bintang, Pemberitahuan $pemberitahuan): int
    {
        $sekarang = CarbonImmutable::now();

        $jadwal->siapkan($sekarang);
        $jumlahBintang = $bintang->proses($sekarang);
        $jumlahHarian = $pemberitahuan->harianMingguIni($sekarang);
        $jumlahPengingat = $pemberitahuan->pengingat($sekarang);

        $this->info("Bintang baru: {$jumlahBintang}. Email harian: {$jumlahHarian}. Pengingat: {$jumlahPengingat}.");

        return self::SUCCESS;
    }
}
