<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Geng;
use App\Models\User;
use App\Services\LinkPribadi;
use Illuminate\Database\Seeder;

/**
 * Data contoh: 15 orang (11 warga + 4 pengurus), 5 orang per geng.
 *
 * Nivia, Dilla, Gita, dan Dewi (kriteria penerimaan) ada di Geng 2, yang menjadi
 * pool piket Kamis pada minggu acuan 16 Nov 2026 (Geng 1 piket harian).
 */
class WargaContohSeeder extends Seeder
{
    /** [nama, kamar, urutan geng, role, wajib_diperiksa] */
    public const DATA = [
        ['Rina Anggraini', '1', 1, Role::TataTertib, false],
        ['Ayu Lestari', '1', 1, Role::Warga, false],
        ['Sari Wulandari', '2', 1, Role::Warga, false],
        ['Putri Ramadhani', '2', 1, Role::Warga, false],
        ['Lestari Handayani', '3', 1, Role::Warga, true],

        ['Nivia Salsabila', '4', 2, Role::Warga, false],
        ['Dilla Maharani', '4', 2, Role::Warga, false],
        ['Gita Permata', '5', 2, Role::Warga, false],
        ['Dewi Kartika', '5', 2, Role::Warga, false],
        ['Maya Safitri', '6', 2, Role::Bendahara, false],

        ['Fitri Amalia', '7', 3, Role::Ketua, false],
        ['Nur Aisyah', '7', 3, Role::Sekretaris, false],
        ['Indah Purnama', '8', 3, Role::Warga, false],
        ['Wulan Sari', '8', 3, Role::Warga, true],
        ['Ratna Dewanti', '9', 3, Role::Warga, false],
    ];

    public function run(LinkPribadi $linkPribadi): void
    {
        $gengs = Geng::query()->pluck('id', 'urutan');
        $links = [];

        foreach (self::DATA as [$nama, $kamar, $urutanGeng, $role, $wajibDiperiksa]) {
            $email = strtolower(strtok($nama, ' ')).'@amkt.test';

            $user = User::query()->updateOrCreate(['email' => $email], [
                'nama' => $nama,
                'no_kamar' => $kamar,
                'geng_id' => $gengs[$urutanGeng],
                'role' => $role,
                'wajib_diperiksa' => $wajibDiperiksa,
                'aktif' => true,
            ]);

            // Link dibuat tanpa mengirim email. Hanya ditampilkan di lingkungan lokal.
            $links[] = [$user->nama, $user->role->label(), $linkPribadi->buat($user)];
        }

        if ($this->command && app()->environment('local')) {
            $this->command->info('Link pribadi data contoh (hanya untuk pengembangan):');
            $this->command->table(['Nama', 'Role', 'Link'], $links);
        }
    }
}
