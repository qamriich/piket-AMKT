<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Models\Geng;
use App\Models\Izin;
use App\Models\MingguPiket;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data untuk halaman warga. Semua yang ditampilkan ke warga hanya dari jadwal yang sudah terbit.
 * Didaftarkan sebagai scoped singleton, jadi hasil query dipakai ulang dalam satu request.
 */
class RingkasanWarga
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private JadwalMinggu $jadwal) {}

    public function sekarang(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    private function ingat(string $kunci, \Closure $hitung): mixed
    {
        return $this->cache[$kunci] ??= $hitung();
    }

    public function lupakan(): void
    {
        $this->cache = [];
    }

    public function mingguIni(): ?MingguPiket
    {
        // siapkan(): sekalian membuat penugasan harian minggu ini dari jadwal periode kalau belum (R1).
        return $this->ingat('minggu', fn () => $this->jadwal->siapkan($this->sekarang())->load('gengHarian'));
    }

    /** Geng harian minggu ini: dari baris minggu kalau sudah dibuat, kalau belum dari rotasi. */
    public function gengHarian(): ?Geng
    {
        return $this->ingat('geng', fn () => $this->mingguIni()?->gengHarian
            ?? RotasiGeng::dariPengaturan()->gengHarian($this->sekarang()));
    }

    /**
     * Piket saya yang relevan sekarang: semua piket minggu ini, ditambah piket minggu lalu
     * yang tenggatnya belum lewat (misalnya piket Minggu yang batasnya Senin 18.00).
     *
     * @return Collection<int, Penugasan>
     */
    public function piketAktif(User $user): Collection
    {
        return $this->ingat("aktif.{$user->id}", function () use ($user) {
            $senin = RotasiGeng::seninDari($this->sekarang());

            return Penugasan::query()
                ->terbit()
                ->where('user_id', $user->id)
                ->where(fn (Builder $q) => $q
                    ->where(fn ($w) => $w->whereDate('tanggal', '>=', $senin->toDateString())->whereDate('tanggal', '<=', $senin->addDays(6)->toDateString()))
                    ->orWhere(fn ($w) => $w->whereIn('status', [StatusPenugasan::Terjadwal->value, StatusPenugasan::BayarBintang->value])
                        ->where('tenggat', '>=', $this->sekarang()->format('Y-m-d H:i:s'))))
                ->with(['tugas', 'membayar.tugas', 'selesaiOleh'])
                ->orderBy('tanggal')
                ->orderBy('id')
                ->get();
        });
    }

    public function adaPiketBelumSelesai(User $user): bool
    {
        return $this->piketAktif($user)->contains(fn (Penugasan $p) => $p->status->belumSelesai()
            && $p->tenggatLokal()->greaterThanOrEqualTo($this->sekarang()));
    }

    public function tenggatTerdekat(User $user): ?Penugasan
    {
        return $this->piketAktif($user)
            ->filter(fn (Penugasan $p) => $p->status->belumSelesai() && $p->tenggatLokal()->greaterThanOrEqualTo($this->sekarang()))
            ->sortBy(fn (Penugasan $p) => $p->tenggat->getTimestamp())
            ->first();
    }

    public function jumlahBintangBelumLunas(User $user): int
    {
        return $this->ingat("bintang.{$user->id}", fn () => Penugasan::query()
            ->bintangBelumLunas()->where('user_id', $user->id)->count());
    }

    public function selesaiBulanIni(User $user): int
    {
        $awal = $this->sekarang()->startOfMonth();

        return Penugasan::query()
            ->where('user_id', $user->id)
            ->where('status', StatusPenugasan::Selesai->value)
            ->whereDate('tanggal', '>=', $awal->toDateString())
            ->whereDate('tanggal', '<=', $awal->endOfMonth()->toDateString())
            ->count();
    }

    public function selesaiTotal(User $user): int
    {
        return Penugasan::query()->where('user_id', $user->id)->where('status', StatusPenugasan::Selesai->value)->count();
    }

    /**
     * Bintang milik warga (bintang asli, bukan baris pembayaran), terbaru di atas.
     *
     * @return Collection<int, Penugasan>
     */
    public function bintangSaya(User $user): Collection
    {
        return Penugasan::query()
            ->where('user_id', $user->id)
            ->whereNull('membayar_penugasan_id')
            ->whereIn('status', [StatusPenugasan::Bintang->value, StatusPenugasan::Lunas->value])
            ->with(['tugas', 'pembayaran.tugas'])
            ->orderByDesc('tanggal')
            ->get();
    }

    /** @return Collection<int, PengajuanGantiBintang> */
    public function pengajuanSaya(User $user): Collection
    {
        return PengajuanGantiBintang::query()
            ->where('user_id', $user->id)
            ->with(['penugasanBintang', 'diputuskanOleh'])
            ->latest()
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Tanggal kosong untuk ganti bintang harian (F8): hari setelah hari ini di minggu yang jadwal
     * hariannya sudah terbit, tanpa petugas harian, dan belum diajukan orang lain.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function tanggalKosong(int $batas = PHP_INT_MAX): Collection
    {
        return $this->ingat("kosong.{$batas}", fn () => app(GantiBintang::class)->tanggalKosong($batas));
    }

    /** @return Collection<int, Izin> Izin milik warga, terbaru di atas. */
    public function izinSaya(User $user): Collection
    {
        return $user->izin()->with('diputuskanOleh')->latest('mulai')->limit(10)->get();
    }

    /** Petugas harian yang terbit untuk satu tanggal. */
    public function harianPada(CarbonImmutable $tanggal): ?Penugasan
    {
        return $this->ingat('harian.'.$tanggal->toDateString(), fn () => Penugasan::query()
            ->terbit()
            ->bukanIzin()
            ->where('jenis', JenisPenugasan::Harian->value)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->with('user.geng')
            ->first());
    }

    /**
     * Isi tab "Jadwal minggu ini".
     *
     * @return array{
     *     harian: array{terbit: bool, baris: list<array{tanggal: CarbonImmutable, penugasan: ?Penugasan}>},
     *     kamis: array{terbit: bool, baris: Collection<int, Penugasan>, bebas: Collection<int, User>},
     *     minggu: array{terbit: bool, kerja_bakti: bool, baris: Collection<int, Penugasan>, bebas: Collection<int, User>}
     * }
     */
    public function jadwalMingguIni(): array
    {
        return $this->ingat('jadwal', function () {
            $m = $this->mingguIni();
            $senin = RotasiGeng::seninDari($this->sekarang());

            $semua = $m
                ? $m->penugasan()->terbit()->with(['user.geng', 'tugas'])->get()
                : collect();

            // Pengganti (bukan Izin) yang ditampilkan kalau di satu hari ada keduanya.
            $harian = $semua->where('jenis', JenisPenugasan::Harian)
                ->sortBy(fn (Penugasan $p) => $p->status === StatusPenugasan::Izin ? 0 : 1)
                ->keyBy(fn (Penugasan $p) => $p->tanggal->toDateString());
            $barisHarian = [];
            for ($i = 0; $i < 7; $i++) {
                $t = $senin->addDays($i);
                $barisHarian[] = ['tanggal' => $t, 'penugasan' => $harian->get($t->toDateString())];
            }

            $urutTugas = fn (Penugasan $p) => [$p->tugas?->urutan ?? 0, $p->id];
            $kamis = $semua->where('jenis', JenisPenugasan::Kamis)->sortBy($urutTugas)->values();
            $minggu = $semua->whereIn('jenis', [JenisPenugasan::Minggu, JenisPenugasan::KerjaBakti])->sortBy($urutTugas)->values();

            $bebas = function (SesiTugas $sesi, Collection $baris) use ($m) {
                if (! $m || $baris->isEmpty()) {
                    return collect();
                }
                $dapat = $baris->pluck('user_id')->all();

                return $this->jadwal->pool($m, $sesi)->reject(fn (User $u) => in_array($u->id, $dapat, true))->values();
            };

            return [
                'harian' => ['terbit' => (bool) $m?->sudahTerbit('harian'), 'baris' => $barisHarian],
                'kamis' => ['terbit' => (bool) $m?->sudahTerbit('kamis'), 'baris' => $kamis, 'bebas' => $bebas(SesiTugas::Kamis, $kamis)],
                'minggu' => [
                    'terbit' => (bool) $m?->sudahTerbit('minggu'),
                    'kerja_bakti' => (bool) $m?->kerjaBakti(),
                    'baris' => $minggu,
                    'bebas' => $m?->kerjaBakti() ? collect() : $bebas(SesiTugas::Minggu, $minggu),
                ],
            ];
        });
    }

    /** Geng yang piket Kamis minggu ini (2 geng non-harian). */
    public function gengKamis(): Collection
    {
        $m = $this->mingguIni();

        return $m ? $this->jadwal->gengKamis($m) : RotasiGeng::dariPengaturan()->gengKamis($this->sekarang());
    }
}
