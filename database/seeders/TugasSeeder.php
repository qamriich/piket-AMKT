<?php

namespace Database\Seeders;

use App\Enums\JenisTugas;
use App\Models\Tugas;
use Illuminate\Database\Seeder;

/**
 * Daftar tugas bawaan (PRD bagian 3).
 */
class TugasSeeder extends Seeder
{
    public const HARIAN = [
        'Dapur (sapu dan pel)',
        'Ruang tamu (sapu dan pel)',
        'Pelataran dalam dan luar asrama (sapu dan pel)',
        'Garasi (sapu dan pel)',
        'Merapikan sepatu di garasi',
        'Menyapu halaman depan',
        'Aula (sapu dan pel)',
        'Membuang sampah dan mencuci tong sampah',
        'Memasak nasi',
    ];

    public const KAMIS_MINGGU = [
        'WC 1',
        'WC 2',
        'WC 3',
        'WC 4',
        'Pelataran depan WC dan cuci baskom',
        'Aula depan',
        'Aula belakang',
        'Halaman 1',
        'Halaman 2',
        'Halaman 3',
        'Jendela kamar',
        'Garasi',
        'Lemari dapur',
    ];

    public function run(): void
    {
        foreach ([JenisTugas::Harian->value => self::HARIAN, JenisTugas::KamisMinggu->value => self::KAMIS_MINGGU] as $jenis => $daftar) {
            foreach ($daftar as $i => $nama) {
                Tugas::query()->updateOrCreate(
                    ['jenis' => $jenis, 'nama' => $nama],
                    ['urutan' => $i + 1, 'aktif_default' => true],
                );
            }
        }
    }
}
