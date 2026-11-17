<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan B3 (penugasan otomatis Kamis dan Minggu) dan B3a (mode kerja bakti).
 *
 * 1. Bintang dulu: bintang yang belum lunas dengan jenis yang sama, dari yang paling lama,
 *    mengunci tugasnya untuk pemiliknya (Bayar Bintang) kalau tugasnya aktif dan pemiliknya
 *    ada di pool. Kalau tidak, bintangnya ditunda ke minggu berikutnya.
 *    Bintang kerja bakti dibayar dengan 1 tugas kecil tambahan dari tugas aktif yang belum dikunci.
 * 2. Tugas reguler: tugas yang belum dikunci dibagikan ke pool, maksimal 1 tugas reguler per orang.
 *    Untuk setiap tugas, pilih orang yang paling lama tidak mengerjakan tugas itu; kalau seri,
 *    yang paling lama tidak mendapat tugas sama sekali; lalu acak.
 * 3. Kalau orang lebih banyak dari tugas, yang paling lama tidak mendapat tugas diprioritaskan,
 *    sehingga yang bebas minggu ini didahulukan minggu berikutnya.
 * 4. Tugas yang tidak kebagian orang dikembalikan sebagai "belum ada petugas".
 */
class PenugasanOtomatis
{
    /** @var Closure(array<int>): array<int> pengacak urutan id untuk pemecah seri terakhir */
    private Closure $acak;

    public function __construct(private JadwalMinggu $jadwal, ?Closure $acak = null)
    {
        $this->acak = $acak ?? function (array $ids): array {
            shuffle($ids);

            return $ids;
        };
    }

    public function denganPengacak(Closure $acak): self
    {
        return new self($this->jadwal, $acak);
    }

    /**
     * Membuat ulang pembagian draf untuk satu sesi.
     *
     * @return array{
     *     penugasan: Collection<int, Penugasan>,
     *     ditunda: list<array{bintang: Penugasan, alasan: string}>,
     *     tanpa_petugas: Collection<int, Tugas>,
     *     bebas: Collection<int, User>
     * }
     */
    public function bagikan(MingguPiket $minggu, SesiTugas $sesi): array
    {
        $this->jadwal->pastikanDraf($minggu, $sesi->value);

        $hasil = DB::transaction(function () use ($minggu, $sesi) {
            // Draf lama sesi ini dihapus dulu.
            $minggu->penugasan()->whereIn('jenis', $sesi->jenisPenugasan())->delete();

            if ($sesi === SesiTugas::Minggu && $minggu->kerjaBakti()) {
                return $this->kerjaBakti($minggu);
            }

            return $this->tugasKecil($minggu, $sesi);
        });

        LogAktivitas::catat('jadwal.buat_pembagian', $minggu, [
            'sesi' => $sesi->value,
            'jumlah' => $hasil['penugasan']->count(),
            'ditunda' => count($hasil['ditunda']),
        ]);

        return $hasil;
    }

    private function kerjaBakti(MingguPiket $minggu): array
    {
        $tanggal = $minggu->hariMinggu();
        $tenggat = Tenggat::untuk(JenisPenugasan::KerjaBakti, $tanggal);

        $penugasan = $this->jadwal->pool($minggu, SesiTugas::Minggu)->map(fn (User $u) => Penugasan::create([
            'minggu_piket_id' => $minggu->id,
            'user_id' => $u->id,
            'jenis' => JenisPenugasan::KerjaBakti,
            'tanggal' => $tanggal->toDateString(),
            'tenggat' => $tenggat,
            'status' => StatusPenugasan::Terjadwal,
        ]));

        // B3a: semua bintang Minggu ditunda ke Minggu berikutnya yang memakai mode tugas kecil.
        $ditunda = $this->bintangTerbuka($minggu, SesiTugas::Minggu)
            ->map(fn (Penugasan $b) => ['bintang' => $b, 'alasan' => 'Minggu ini kerja bakti'])
            ->values()->all();

        return ['penugasan' => $penugasan, 'ditunda' => $ditunda, 'tanpa_petugas' => collect(), 'bebas' => collect()];
    }

    private function tugasKecil(MingguPiket $minggu, SesiTugas $sesi): array
    {
        $jenis = $sesi === SesiTugas::Kamis ? JenisPenugasan::Kamis : JenisPenugasan::Minggu;
        $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();
        $tenggat = Tenggat::untuk($jenis, $tanggal);

        $tugas = $this->jadwal->tugasAktif($minggu, $sesi)->keyBy('id');
        $pool = $this->jadwal->pool($minggu, $sesi)->keyBy('id');
        [$terakhirTugas, $terakhirApaPun] = $this->riwayat($pool->keys()->all(), $minggu, $tanggal);

        // Langkah 1: bintang dulu.
        $kunci = []; // tugas_id => [user_id, bintang_id]
        $ditunda = [];

        foreach ($this->bintangTerbuka($minggu, $sesi) as $bintang) {
            $alasan = null;

            if (! $pool->has($bintang->user_id)) {
                $alasan = match (true) {
                    $bintang->user?->izinUntuk($sesi === SesiTugas::Kamis ? JenisPenugasan::Kamis : JenisPenugasan::Minggu, $tanggal) => 'Pemiliknya sedang izin',
                    $sesi === SesiTugas::Kamis => 'Pemiliknya sedang piket harian atau tidak aktif',
                    default => 'Pemiliknya tidak aktif',
                };
            } elseif ($bintang->jenis === JenisPenugasan::KerjaBakti) {
                // B3a: 1 tugas kecil tambahan dari tugas aktif yang belum dikunci.
                $tersedia = $tugas->keys()->diff(array_keys($kunci))->values();
                if ($tersedia->isEmpty()) {
                    $alasan = 'Tidak ada tugas tersisa';
                } else {
                    $pilih = $tersedia->sortBy(fn (int $tid) => [
                        $terakhirTugas[$bintang->user_id][$tid] ?? '',
                        $tugas[$tid]->urutan,
                        $tid,
                    ])->first();
                    $kunci[$pilih] = [$bintang->user_id, $bintang->id];
                }
            } elseif (! $tugas->has($bintang->tugas_id)) {
                $alasan = 'Tugasnya tidak aktif minggu ini';
            } elseif (isset($kunci[$bintang->tugas_id])) {
                $alasan = 'Tugasnya sudah dikunci bintang lain';
            } else {
                $kunci[$bintang->tugas_id] = [$bintang->user_id, $bintang->id];
            }

            if ($alasan !== null) {
                $ditunda[] = ['bintang' => $bintang, 'alasan' => $alasan];
            }
        }

        // Langkah 2: tugas reguler.
        $reguler = $tugas->reject(fn (Tugas $t) => isset($kunci[$t->id]))->values();

        $acakUrut = array_flip(($this->acak)($pool->keys()->all())); // user_id => peringkat acak
        $belumDapat = fn (int $id) => $terakhirApaPun[$id] ?? '';

        $peserta = $pool->keys()->all();
        if (count($peserta) > $reguler->count()) {
            // Orang lebih banyak dari tugas: yang paling lama tidak dapat tugas didahulukan.
            usort($peserta, fn (int $a, int $b) => [$belumDapat($a), $acakUrut[$a]] <=> [$belumDapat($b), $acakUrut[$b]]);
            $peserta = array_slice($peserta, 0, $reguler->count());
        }

        $bagi = []; // tugas_id => user_id
        $tanpaPetugas = collect();

        foreach ($reguler as $t) {
            if ($peserta === []) {
                $tanpaPetugas->push($t);

                continue;
            }

            usort($peserta, fn (int $a, int $b) => [
                $terakhirTugas[$a][$t->id] ?? '', $belumDapat($a), $acakUrut[$a],
            ] <=> [
                $terakhirTugas[$b][$t->id] ?? '', $belumDapat($b), $acakUrut[$b],
            ]);

            $bagi[$t->id] = array_shift($peserta);
        }

        $buat = fn (int $tugasId, int $userId, StatusPenugasan $status, ?int $membayar = null) => Penugasan::create([
            'minggu_piket_id' => $minggu->id,
            'user_id' => $userId,
            'tugas_id' => $tugasId,
            'jenis' => $jenis,
            'tanggal' => $tanggal->toDateString(),
            'tenggat' => $tenggat,
            'status' => $status,
            'membayar_penugasan_id' => $membayar,
        ]);

        $penugasan = collect();
        foreach ($tugas as $t) {
            if (isset($kunci[$t->id])) {
                $penugasan->push($buat($t->id, $kunci[$t->id][0], StatusPenugasan::BayarBintang, $kunci[$t->id][1]));
            } elseif (isset($bagi[$t->id])) {
                $penugasan->push($buat($t->id, $bagi[$t->id], StatusPenugasan::Terjadwal));
            }
        }

        $dapat = $penugasan->pluck('user_id')->unique();
        $bebas = $pool->reject(fn (User $u) => $dapat->contains($u->id))->values();

        return ['penugasan' => $penugasan, 'ditunda' => $ditunda, 'tanpa_petugas' => $tanpaPetugas, 'bebas' => $bebas];
    }

    /**
     * R3: pengganti untuk satu tugas yang dilepas karena izin, dengan aturan B3 langkah 2:
     * warga di pool yang sedang di asrama (pool sudah tanpa warga yang izin), belum punya tugas reguler
     * di sesi itu, dan paling lama tidak mengerjakan tugas tersebut. Seri: paling lama tidak dapat tugas, lalu acak.
     *
     * @param  list<int>  $kecuali
     */
    public function pilihPengganti(MingguPiket $minggu, SesiTugas $sesi, int $tugasId, array $kecuali = []): ?User
    {
        $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();
        $pool = $this->jadwal->pool($minggu, $sesi)->keyBy('id');

        // Tugas reguler = bukan baris Bayar Bintang (pemilik bintang tetap boleh dapat 1 tugas reguler).
        $punyaReguler = $minggu->penugasan()->bukanIzin()
            ->whereIn('jenis', $sesi->jenisPenugasan())
            ->whereNull('membayar_penugasan_id')
            ->pluck('user_id')->all();

        $calon = $pool->keys()->diff($punyaReguler)->diff($kecuali)->values()->all();
        if ($calon === []) {
            return null;
        }

        [$terakhirTugas, $terakhirApaPun] = $this->riwayat($calon, $minggu, $tanggal);
        $acakUrut = array_flip(($this->acak)($calon));

        usort($calon, fn (int $a, int $b) => [
            $terakhirTugas[$a][$tugasId] ?? '', $terakhirApaPun[$a] ?? '', $acakUrut[$a],
        ] <=> [
            $terakhirTugas[$b][$tugasId] ?? '', $terakhirApaPun[$b] ?? '', $acakUrut[$b],
        ]);

        return $pool[$calon[0]];
    }

    /**
     * Bintang Kamis (untuk Kamis) atau bintang Minggu dan kerja bakti (untuk Minggu) yang belum lunas,
     * dari yang paling lama. Bintang yang sudah punya Bayar Bintang yang sedang berjalan di minggu lain
     * tidak diambil lagi, supaya tidak dijadwalkan dua kali.
     *
     * @return Collection<int, Penugasan>
     */
    public function bintangTerbuka(MingguPiket $minggu, SesiTugas $sesi): Collection
    {
        $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();

        return Penugasan::query()
            ->bintangBelumLunas()
            ->whereIn('jenis', $sesi->jenisPenugasan())
            ->whereDate('tanggal', '<', $tanggal->toDateString())
            ->whereDoesntHave('pembayaran', fn ($q) => $q
                ->where('status', StatusPenugasan::BayarBintang->value)
                ->where('minggu_piket_id', '!=', $minggu->id))
            ->with(['tugas', 'user'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    /**
     * Riwayat tugas Kamis/Minggu sebelum minggu ini.
     *
     * @param  list<int>  $userIds
     * @return array{0: array<int, array<int, string>>, 1: array<int, string>}
     *                                                                         [user_id => [tugas_id => tanggal terakhir]],
     *                                                                         [user_id => tanggal terakhir dapat tugas]
     */
    private function riwayat(array $userIds, MingguPiket $minggu, CarbonImmutable $sebelum): array
    {
        // Kerja bakti diikuti semua orang, jadi tidak dihitung sebagai "mendapat tugas".
        $baris = Penugasan::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('jenis', [JenisPenugasan::Kamis->value, JenisPenugasan::Minggu->value])
            ->where('status', '!=', StatusPenugasan::Izin->value) // tugas yang dilepas karena izin tidak dihitung
            ->where('minggu_piket_id', '!=', $minggu->id)
            ->whereDate('tanggal', '<', $sebelum->toDateString())
            ->get(['user_id', 'tugas_id', 'tanggal']);

        $perTugas = [];
        $apaPun = [];
        foreach ($baris as $b) {
            $tgl = $b->tanggal->toDateString();
            $perTugas[$b->user_id][$b->tugas_id] = max($perTugas[$b->user_id][$b->tugas_id] ?? '', $tgl);
            $apaPun[$b->user_id] = max($apaPun[$b->user_id] ?? '', $tgl);
        }

        return [$perTugas, $apaPun];
    }
}
