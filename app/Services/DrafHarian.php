<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Models\Geng;
use App\Models\JadwalKuliah;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Aturan B2: saran pembagian hari piket harian. Sejak revisi R1 (PRD bagian 15) dipakai untuk
 * menyusun draf jadwal harian PERIODE per geng, bukan per minggu.
 *
 * 1. Kandidat = anggota aktif geng.
 * 2. Warga *bisa* pada hari D kalau tidak ada kuliah hari itu, atau kuliah pertamanya
 *    dimulai pada/setelah batas_jam_kuliah.
 * 3. Maksimal 1 hari per orang. Warga dengan hari *bisa* paling sedikit diisi lebih dulu.
 *    Kalau ada beberapa pilihan, utamakan hari yang berbeda dari giliran sebelumnya.
 * 4. Hari yang tidak terisi tetap kosong (dipakai untuk bayar bintang harian).
 * 5. Warga yang tidak bisa di hari mana pun (atau tidak kebagian hari) masuk peringatan.
 */
class DrafHarian
{
    /**
     * @return array{
     *     jadwal: array<int, int|null>,
     *     bisa: array<int, list<int>>,
     *     peringatan: list<string>
     * } jadwal: hari ISO (1–7) => user_id (null = kosong); bisa: user_id => hari ISO
     */
    public function susunUntukGeng(Geng $geng): array
    {
        $kandidat = User::query()->aktif()->where('geng_id', $geng->id)->orderBy('id')->get();
        $bisa = $this->hariBisa($kandidat);
        $sebelumnya = $this->hariGiliranSebelumnya($kandidat);

        $terisi = []; // hari ISO => user_id
        $peringatan = [];

        // Paling sedikit hari bisa diisi lebih dulu; seri diurutkan id supaya hasilnya stabil.
        $urutan = $kandidat->sortBy(fn (User $u) => [count($bisa[$u->id]), $u->id])->values();
        $sisa = $urutan->pluck('id')->all();

        foreach ($urutan as $user) {
            $sisa = array_values(array_diff($sisa, [$user->id]));
            $hariBisa = $bisa[$user->id];

            if ($hariBisa === []) {
                $peringatan[] = "{$user->nama} tidak bisa di hari mana pun karena kuliahnya selalu pagi. Tentukan harinya secara manual.";

                continue;
            }

            $pilihan = array_values(array_filter($hariBisa, fn (int $h) => ! array_key_exists($h, $terisi)));
            if ($pilihan === []) {
                $peringatan[] = "{$user->nama} belum dapat hari karena semua hari yang bisa sudah terisi. Tentukan secara manual.";

                continue;
            }

            // Utamakan hari yang berbeda dari giliran sebelumnya.
            $berbeda = array_values(array_filter($pilihan, fn (int $h) => $h !== ($sebelumnya[$user->id] ?? null)));
            if ($berbeda !== []) {
                $pilihan = $berbeda;
            }

            // Pilih hari yang paling sedikit diperebutkan kandidat lain, supaya mereka tetap kebagian.
            usort($pilihan, function (int $a, int $b) use ($sisa, $bisa) {
                $peminat = fn (int $h) => count(array_filter($sisa, fn (int $id) => in_array($h, $bisa[$id], true)));

                return [$peminat($a), $a] <=> [$peminat($b), $b];
            });

            $terisi[$pilihan[0]] = $user->id;
        }

        $jadwal = [];
        for ($h = 1; $h <= 7; $h++) {
            $jadwal[$h] = $terisi[$h] ?? null;
        }

        return ['jadwal' => $jadwal, 'bisa' => $bisa, 'peringatan' => $peringatan];
    }

    /**
     * @param  Collection<int, User>  $kandidat
     * @return array<int, list<int>> user_id => hari ISO yang bisa
     */
    public function hariBisa(Collection $kandidat): array
    {
        $batas = substr((string) Pengaturan::ambil('batas_jam_kuliah', '09:00'), 0, 5);

        $kuliahPertama = JadwalKuliah::query()
            ->whereIn('user_id', $kandidat->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $baris) => $baris->groupBy('hari')->map(
                fn (Collection $perHari) => $perHari->map(fn ($k) => substr((string) $k->jam_mulai, 0, 5))->min()
            ));

        $hasil = [];
        foreach ($kandidat as $user) {
            $hasil[$user->id] = [];
            for ($h = 1; $h <= 7; $h++) {
                $mulai = $kuliahPertama[$user->id][$h] ?? null;
                if ($mulai === null || $mulai >= $batas) {
                    $hasil[$user->id][] = $h;
                }
            }
        }

        return $hasil;
    }

    /** @return array<int, int> user_id => hari ISO giliran harian terakhir sebelum hari ini */
    private function hariGiliranSebelumnya(Collection $kandidat): array
    {
        return Penugasan::query()
            ->where('jenis', JenisPenugasan::Harian->value)
            ->whereIn('user_id', $kandidat->pluck('id'))
            ->whereDate('tanggal', '<', now()->toDateString())
            ->orderBy('tanggal')
            ->get()
            ->mapWithKeys(fn (Penugasan $p) => [$p->user_id => $p->tanggalLokal()->dayOfWeekIso])
            ->all();
    }
}
