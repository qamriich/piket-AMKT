<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPenugasan;
use App\Models\JadwalKuliah;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan B2: saran pembagian hari piket harian.
 *
 * 1. Kandidat = anggota aktif geng yang bertugas.
 * 2. Warga *bisa* pada hari D kalau tidak ada kuliah hari itu, atau kuliah pertamanya
 *    dimulai pada/setelah batas_jam_kuliah.
 * 3. Maksimal 1 hari per orang. Warga dengan hari *bisa* paling sedikit diisi lebih dulu.
 *    Kalau ada beberapa pilihan, utamakan hari yang berbeda dari giliran sebelumnya.
 * 4. Hari yang tidak terisi tetap kosong (dipakai untuk bayar bintang harian).
 * 5. Warga yang tidak bisa di hari mana pun (atau tidak kebagian hari) masuk peringatan.
 */
class DrafHarian
{
    public function __construct(private JadwalMinggu $jadwal) {}

    /**
     * Menghitung saran tanpa menyimpan.
     *
     * @return array{
     *     jadwal: array<string, int|null>,
     *     bisa: array<int, list<int>>,
     *     peringatan: list<string>
     * } jadwal: tanggal Y-m-d => user_id (null = kosong); bisa: user_id => hari ISO (1–7)
     */
    public function susun(MingguPiket $minggu): array
    {
        $senin = $minggu->senin();
        $tanggal = [];
        for ($h = 1; $h <= 7; $h++) {
            $tanggal[$h] = $senin->addDays($h - 1);
        }

        // Hari yang sudah terkunci (Bayar Bintang dari pengajuan, atau sudah dikerjakan) tidak disentuh.
        $terkunci = Penugasan::query()
            ->where('jenis', JenisPenugasan::Harian->value)
            ->where('status', '!=', StatusPenugasan::Terjadwal->value)
            ->whereDate('tanggal', '>=', $tanggal[1]->toDateString())
            ->whereDate('tanggal', '<=', $tanggal[7]->toDateString())
            ->get()
            ->mapWithKeys(fn (Penugasan $p) => [$p->tanggalLokal()->dayOfWeekIso => $p->user_id]);

        $kandidat = $this->jadwal->anggotaHarian($minggu)->sortBy('id')->values();
        $bisa = $this->hariBisa($kandidat);
        $sebelumnya = $this->hariGiliranSebelumnya($kandidat, $senin);

        $terisi = $terkunci->all(); // hari ISO => user_id
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
        foreach ($tanggal as $h => $t) {
            $jadwal[$t->toDateString()] = $terisi[$h] ?? null;
        }

        return ['jadwal' => $jadwal, 'bisa' => $bisa, 'peringatan' => $peringatan];
    }

    /**
     * Menyusun ulang draf: penugasan harian berstatus Terjadwal di minggu ini diganti dengan saran baru.
     *
     * @return list<string> peringatan
     */
    public function simpan(MingguPiket $minggu): array
    {
        $this->jadwal->pastikanDraf($minggu, 'harian');
        $hasil = $this->susun($minggu);

        DB::transaction(function () use ($minggu, $hasil) {
            $minggu->penugasan()
                ->where('jenis', JenisPenugasan::Harian->value)
                ->where('status', StatusPenugasan::Terjadwal->value)
                ->delete();

            $terkunci = Penugasan::query()->where('jenis', 'harian')
                ->whereIn(DB::raw('date(tanggal)'), array_keys($hasil['jadwal']))
                ->get()->map(fn (Penugasan $p) => $p->tanggalLokal()->toDateString())->all();

            foreach ($hasil['jadwal'] as $tgl => $userId) {
                if ($userId === null || in_array($tgl, $terkunci, true)) {
                    continue;
                }
                $t = CarbonImmutable::parse($tgl, config('app.timezone'));
                Penugasan::create([
                    'minggu_piket_id' => $minggu->id,
                    'user_id' => $userId,
                    'jenis' => JenisPenugasan::Harian,
                    'tanggal' => $tgl,
                    'tenggat' => Tenggat::untuk(JenisPenugasan::Harian, $t),
                    'status' => StatusPenugasan::Terjadwal,
                ]);
            }
        });

        LogAktivitas::catat('jadwal.draf_harian', $minggu, ['peringatan' => $hasil['peringatan']]);

        return $hasil['peringatan'];
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

    /** @return array<int, int> user_id => hari ISO giliran harian terakhir sebelum minggu ini */
    private function hariGiliranSebelumnya(Collection $kandidat, CarbonImmutable $senin): array
    {
        return Penugasan::query()
            ->where('jenis', JenisPenugasan::Harian->value)
            ->whereIn('user_id', $kandidat->pluck('id'))
            ->whereDate('tanggal', '<', $senin->toDateString())
            ->orderBy('tanggal')
            ->get()
            ->mapWithKeys(fn (Penugasan $p) => [$p->user_id => $p->tanggalLokal()->dayOfWeekIso])
            ->all();
    }
}
