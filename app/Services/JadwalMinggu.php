<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\ModeMinggu;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Enums\StatusTerbit;
use App\Models\Geng;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\TugasAktif;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan satu minggu piket: membuat baris minggu, toggle tugas (F5),
 * mode Minggu, terbitkan, serta perubahan petugas secara manual.
 */
class JadwalMinggu
{
    public function cari(CarbonInterface $tanggal): ?MingguPiket
    {
        return MingguPiket::query()
            ->whereDate('tanggal_senin', RotasiGeng::seninDari($tanggal)->toDateString())
            ->first();
    }

    /**
     * Mengambil atau membuat baris minggu. Saat dibuat, geng harian dihitung dari rotasi (B1),
     * lalu toggle tugas Kamis/Minggu dan mode Minggu mengikuti minggu sebelumnya (F5).
     * Kalau belum ada minggu sebelumnya, toggle memakai "aktif default" dari master tugas.
     */
    public function siapkan(CarbonInterface $tanggal): MingguPiket
    {
        $senin = RotasiGeng::seninDari($tanggal);

        if ($ada = $this->cari($senin)) {
            return $ada;
        }

        return DB::transaction(function () use ($senin) {
            $sebelumnya = MingguPiket::query()
                ->whereDate('tanggal_senin', '<', $senin->toDateString())
                ->orderByDesc('tanggal_senin')
                ->first();

            $geng = RotasiGeng::dariPengaturan()->gengHarian($senin);
            abort_if($geng === null, 500, 'Data geng belum ada.');

            $minggu = MingguPiket::create([
                'tanggal_senin' => $senin->toDateString(),
                'geng_harian_id' => $geng->id,
                'mode_minggu' => $sebelumnya?->mode_minggu ?? ModeMinggu::TugasKecil,
            ]);

            $tugasIds = Tugas::query()->jenis(JenisTugas::KamisMinggu)->pluck('aktif_default', 'id');

            foreach (SesiTugas::cases() as $sesi) {
                $aktif = $sebelumnya
                    ? $sebelumnya->tugasAktif()->where('sesi', $sesi->value)->pluck('tugas_id')->intersect($tugasIds->keys())
                    : $tugasIds->filter()->keys();

                foreach ($aktif as $tugasId) {
                    TugasAktif::create(['minggu_piket_id' => $minggu->id, 'tugas_id' => $tugasId, 'sesi' => $sesi->value]);
                }
            }

            return $minggu;
        });
    }

    /** @return Collection<int, Tugas> Tugas aktif sesi ini, urut. */
    public function tugasAktif(MingguPiket $minggu, SesiTugas $sesi): Collection
    {
        return Tugas::query()
            ->jenis(JenisTugas::KamisMinggu)
            ->whereIn('id', $minggu->tugasAktif()->where('sesi', $sesi->value)->select('tugas_id'))
            ->urut()
            ->get();
    }

    /**
     * Pool petugas (B3): Kamis = anggota aktif 2 geng non-harian, Minggu = semua warga aktif.
     *
     * @return Collection<int, User>
     */
    public function pool(MingguPiket $minggu, SesiTugas $sesi): Collection
    {
        return User::query()
            ->aktif()
            ->when($sesi === SesiTugas::Kamis, fn ($q) => $q
                ->whereNotNull('geng_id')
                ->where('geng_id', '!=', $minggu->geng_harian_id))
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, User> Anggota aktif geng harian minggu ini. */
    public function anggotaHarian(MingguPiket $minggu): Collection
    {
        return User::query()->aktif()->where('geng_id', $minggu->geng_harian_id)->orderBy('nama')->get();
    }

    public function ubahTugasAktif(MingguPiket $minggu, SesiTugas $sesi, Tugas $tugas, bool $aktif): void
    {
        $this->pastikanDraf($minggu, $sesi->value);

        DB::transaction(function () use ($minggu, $sesi, $tugas, $aktif) {
            if ($aktif) {
                TugasAktif::query()->firstOrCreate(['minggu_piket_id' => $minggu->id, 'tugas_id' => $tugas->id, 'sesi' => $sesi->value]);
            } else {
                TugasAktif::query()->where(['minggu_piket_id' => $minggu->id, 'tugas_id' => $tugas->id, 'sesi' => $sesi->value])->delete();
                // Petugas di draf untuk tugas yang dimatikan ikut dihapus.
                $minggu->penugasan()->where('tugas_id', $tugas->id)->whereIn('jenis', $sesi->jenisPenugasan())->delete();
            }
        });

        LogAktivitas::catat('jadwal.tugas_aktif', $minggu, ['sesi' => $sesi->value, 'tugas' => $tugas->nama, 'aktif' => $aktif]);
    }

    public function ubahModeMinggu(MingguPiket $minggu, ModeMinggu $mode): void
    {
        $this->pastikanDraf($minggu, 'minggu');

        if ($minggu->mode_minggu === $mode) {
            return;
        }

        DB::transaction(function () use ($minggu, $mode) {
            $minggu->update(['mode_minggu' => $mode]);
            // Pembagian lama tidak berlaku untuk mode yang baru.
            $minggu->penugasan()->whereIn('jenis', SesiTugas::Minggu->jenisPenugasan())->delete();
        });

        LogAktivitas::catat('jadwal.mode_minggu', $minggu, ['mode' => $mode->value]);
    }

    /** @param  string  $bagian  harian | kamis | minggu */
    public function terbitkan(MingguPiket $minggu, string $bagian): void
    {
        $this->pastikanDraf($minggu, $bagian);

        $jenis = $bagian === 'harian' ? ['harian'] : SesiTugas::from($bagian)->jenisPenugasan();
        if (! $minggu->penugasan()->whereIn('jenis', $jenis)->exists()) {
            throw ValidationException::withMessages(['jadwal' => 'Belum ada pembagian yang bisa diterbitkan.']);
        }

        $minggu->update(['status_'.$bagian => StatusTerbit::Terbit]);
        LogAktivitas::catat('jadwal.terbit', $minggu, ['bagian' => $bagian]);
    }

    /** Menukar petugas dua tugas reguler di sesi yang sama. Tugas Bayar Bintang terkunci untuk pemiliknya. */
    public function tukar(Penugasan $a, Penugasan $b): void
    {
        if ($a->minggu_piket_id !== $b->minggu_piket_id || $a->jenis !== $b->jenis || $a->is($b)) {
            throw ValidationException::withMessages(['tukar' => 'Hanya bisa menukar petugas di jadwal yang sama.']);
        }
        foreach ([$a, $b] as $p) {
            if ($p->status !== StatusPenugasan::Terjadwal) {
                throw ValidationException::withMessages(['tukar' => 'Tugas Bayar Bintang atau yang sudah dikerjakan tidak bisa ditukar.']);
            }
        }

        DB::transaction(function () use ($a, $b) {
            [$ua, $ub] = [$a->user_id, $b->user_id];
            $a->update(['user_id' => $ub]);
            $b->update(['user_id' => $ua]);
        });

        LogAktivitas::catat('jadwal.tukar', $a, ['dengan' => $b->id]);
    }

    /** Mengganti petugas satu tugas reguler, atau mengisi tugas yang belum ada petugasnya. */
    public function aturPetugasTugas(MingguPiket $minggu, SesiTugas $sesi, Tugas $tugas, ?User $user): void
    {
        $jenis = $sesi === SesiTugas::Kamis ? JenisPenugasan::Kamis : JenisPenugasan::Minggu;
        $baris = $minggu->penugasan()->where('tugas_id', $tugas->id)->where('jenis', $jenis->value)->first();

        if ($baris && $baris->status !== StatusPenugasan::Terjadwal) {
            throw ValidationException::withMessages(['petugas' => 'Tugas ini terkunci (Bayar Bintang atau sudah dikerjakan).']);
        }
        if ($user && ! $user->aktif) {
            throw ValidationException::withMessages(['petugas' => 'Warga ini tidak aktif.']);
        }
        if (! $this->tugasAktif($minggu, $sesi)->contains('id', $tugas->id)) {
            throw ValidationException::withMessages(['petugas' => 'Tugas ini tidak aktif minggu ini.']);
        }

        if ($user === null) {
            $baris?->delete();
        } elseif ($baris) {
            $baris->update(['user_id' => $user->id]);
        } else {
            $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();
            Penugasan::create([
                'minggu_piket_id' => $minggu->id,
                'user_id' => $user->id,
                'tugas_id' => $tugas->id,
                'jenis' => $jenis,
                'tanggal' => $tanggal->toDateString(),
                'tenggat' => Tenggat::untuk($jenis, $tanggal),
                'status' => StatusPenugasan::Terjadwal,
            ]);
        }

        LogAktivitas::catat('jadwal.ubah_petugas', $minggu, ['sesi' => $sesi->value, 'tugas' => $tugas->nama, 'user_id' => $user?->id]);
    }

    /** Mengisi atau mengosongkan satu hari piket harian (F4). */
    public function aturHarian(MingguPiket $minggu, CarbonImmutable $tanggal, ?User $user): void
    {
        $senin = $minggu->senin();
        if ($tanggal->lt($senin) || $tanggal->gt($senin->addDays(6))) {
            throw ValidationException::withMessages(['harian' => 'Tanggal di luar minggu ini.']);
        }

        $baris = Penugasan::query()->where('jenis', 'harian')->whereDate('tanggal', $tanggal->toDateString())->first();
        if ($baris && $baris->status !== StatusPenugasan::Terjadwal) {
            throw ValidationException::withMessages(['harian' => 'Hari ini sudah terisi Bayar Bintang atau sudah dikerjakan.']);
        }
        if ($user && (! $user->aktif || $user->geng_id !== $minggu->geng_harian_id)) {
            throw ValidationException::withMessages(['harian' => 'Pilih anggota aktif geng yang bertugas minggu ini.']);
        }

        if ($user === null) {
            $baris?->delete();
        } elseif ($baris) {
            $baris->update(['user_id' => $user->id]);
        } else {
            Penugasan::create([
                'minggu_piket_id' => $minggu->id,
                'user_id' => $user->id,
                'jenis' => JenisPenugasan::Harian,
                'tanggal' => $tanggal->toDateString(),
                'tenggat' => Tenggat::untuk(JenisPenugasan::Harian, $tanggal),
                'status' => StatusPenugasan::Terjadwal,
            ]);
        }

        LogAktivitas::catat('jadwal.ubah_harian', $minggu, ['tanggal' => $tanggal->toDateString(), 'user_id' => $user?->id]);
    }

    public function pastikanDraf(MingguPiket $minggu, string $bagian): void
    {
        if ($minggu->sudahTerbit($bagian)) {
            throw ValidationException::withMessages(['jadwal' => 'Jadwal ini sudah diterbitkan, jadi daftar tugasnya tidak bisa diubah lagi.']);
        }
    }

    /** Geng-geng non-harian minggu ini (pool Kamis). */
    public function gengKamis(MingguPiket $minggu): Collection
    {
        return Geng::query()->where('id', '!=', $minggu->geng_harian_id)->orderBy('urutan')->get();
    }
}
