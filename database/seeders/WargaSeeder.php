<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Geng;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data warga awal (PRD bagian 13): 15 orang, nama panggilan, email sementara <nama>@amkt.test,
 * nomor kamar dikosongkan. Pengurus mengganti email dari halaman Warga & geng.
 *
 * Password: HANYA di lingkungan lokal semua akun diberi password awal sementara (config piket.password_lokal,
 * default "piket123") supaya bisa dicoba; tetap wajib diganti saat login pertama (R2). Di lingkungan lain
 * password dibiarkan kosong; pengurus mengisinya lewat "Reset password" di halaman Warga & geng.
 */
class WargaSeeder extends Seeder
{
    /** [nama, role, urutan geng, wajib_diperiksa] */
    public const DATA = [
        ['Gita', Role::Ketua, 1, false],
        ['Qamri', Role::Sekretaris, 1, false],
        ['Adel', Role::Bendahara, 3, false],
        ['Wilda', Role::TataTertib, 3, false],
        ['Ping', Role::Warga, 2, false],
        ['Ain', Role::Warga, 3, false],
        ['Pipi', Role::Warga, 2, false],
        ['Fika', Role::Warga, 3, false],
        ['Nivia', Role::Warga, 2, true],
        ['Niya', Role::Warga, 3, true],
        ['Eca', Role::Warga, 2, true],
        ['Dilla', Role::Warga, 1, true],
        ['Dewi', Role::Warga, 1, true],
        ['Kia', Role::Warga, 2, true],
        ['Septi', Role::Warga, 1, false],
    ];

    public function run(): void
    {
        $gengs = Geng::query()->pluck('id', 'urutan');
        $lokal = app()->environment('local');
        $passwordLokal = (string) config('piket.password_lokal');

        foreach (self::DATA as [$nama, $role, $urutanGeng, $wajibDiperiksa]) {
            $user = User::query()->updateOrCreate(['email' => strtolower($nama).'@amkt.test'], [
                'nama' => $nama,
                'no_kamar' => null,
                'geng_id' => $gengs[$urutanGeng],
                'role' => $role,
                'wajib_diperiksa' => $wajibDiperiksa,
                'aktif' => true,
            ]);

            if ($lokal) {
                $user->aturPasswordAwal($passwordLokal);
            }
        }

        if ($this->command) {
            $this->command->info(count(self::DATA).' akun warga dibuat (email <nama>@amkt.test).');
            $lokal
                ? $this->command->warn("Lokal: semua akun memakai password awal \"{$passwordLokal}\" (wajib diganti saat login pertama).")
                : $this->command->warn('Password belum ada. Isi lewat "Reset password" di halaman Warga & geng.');
        }
    }
}
