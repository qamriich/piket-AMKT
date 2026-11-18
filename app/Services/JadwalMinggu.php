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
    public function __construct(private JadwalHarianPeriode $periode, private Pemberitahuan $pemberitahuan) {}

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
        $minggu = $this->cariAtauBuat(RotasiGeng::seninDari($tanggal));

        // R1: penugasan harian dibuat otomatis dari jadwal periode (sekali per minggu).
        if ($this->periode->terapkan($minggu) > 0) {
            $minggu->refresh();
        }

        return $minggu;
    }

    private function cariAtauBuat(CarbonImmutable $senin): MingguPiket
    {
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
     * Warga yang izinnya menutupi seluruh waktu piket sesi itu tidak masuk pool (R3, R3a). Izin sebagian tetap masuk pool.
     *
     * @return Collection<int, User>
     */
    public function pool(MingguPiket $minggu, SesiTugas $sesi): Collection
    {
        $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();

        return User::query()
            ->aktif()
            ->tidakIzinUntuk($sesi === SesiTugas::Kamis ? JenisPenugasan::Kamis : JenisPenugasan::Minggu, $tanggal)
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

    /**
     * Menerbitkan Kamis atau Minggu, lalu mengirim email ke semua warga aktif (bagian 8).
     *
     * @param  string  $bagian  kamis | minggu. Harian tidak diterbitkan: dibuat otomatis dari periode (R1).
     */
    public function terbitkan(MingguPiket $minggu, string $bagian): void
    {
        $sesi = SesiTugas::tryFrom($bagian);
        if ($sesi === null) {
            throw ValidationException::withMessages(['jadwal' => 'Jadwal harian dibuat otomatis dari jadwal periode, tidak perlu diterbitkan.']);
        }

        $this->pastikanDraf($minggu, $bagian);

        $jenis = $sesi->jenisPenugasan();
        if (! $minggu->penugasan()->whereIn('jenis', $jenis)->exists()) {
            throw ValidationException::withMessages(['jadwal' => 'Belum ada pembagian yang bisa diterbitkan.']);
        }

        $minggu->update(['status_'.$bagian => StatusTerbit::Terbit]);
        LogAktivitas::catat('jadwal.terbit', $minggu, ['bagian' => $bagian]);

        $this->pemberitahuan->jadwalTerbit($minggu, $sesi);
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

        // B8: setelah terbit, hanya dua warga yang ditukar yang diberi tahu.
        if ($a->loadMissing('mingguPiket')->mingguPiket->sudahTerbit($a->jenis)) {
            foreach ([[$a, $b], [$b, $a]] as [$baru, $lama]) {
                $baru->load(['user', 'tugas']);
                $lama->loadMissing('tugas');
                $this->pemberitahuan->berubah($baru->user, 'Piketmu berubah: '.$baru->namaTugas(), [
                    'Pengurus menukar petugas piket '.$baru->jenis->label().'.',
                    'Sekarang: '.$baru->ringkasan(),
                    'Sebelumnya: '.$lama->namaTugas(),
                ]);
            }
        }
    }

    /** Mengganti petugas satu tugas reguler, atau mengisi tugas yang belum ada petugasnya. */
    public function aturPetugasTugas(MingguPiket $minggu, SesiTugas $sesi, Tugas $tugas, ?User $user): void
    {
        $jenis = $sesi === SesiTugas::Kamis ? JenisPenugasan::Kamis : JenisPenugasan::Minggu;
        $baris = $minggu->penugasan()->bukanIzin()->where('tugas_id', $tugas->id)->where('jenis', $jenis->value)->with('user')->first();
        $lama = $baris?->user;

        if ($baris && $baris->status !== StatusPenugasan::Terjadwal) {
            throw ValidationException::withMessages(['petugas' => 'Tugas ini terkunci (Bayar Bintang atau sudah dikerjakan).']);
        }
        if ($user && ! $user->aktif) {
            throw ValidationException::withMessages(['petugas' => 'Warga ini tidak aktif.']);
        }
        if (! $this->tugasAktif($minggu, $sesi)->contains('id', $tugas->id)) {
            throw ValidationException::withMessages(['petugas' => 'Tugas ini tidak aktif minggu ini.']);
        }

        $terbit = $minggu->sudahTerbit($sesi);
        $tandai = $terbit ? ['diberitahu_pada' => CarbonImmutable::now()] : [];

        if ($user === null) {
            $baris?->delete();
            $baru = null;
        } elseif ($baris) {
            $baris->update(['user_id' => $user->id] + $tandai);
            $baru = $baris;
        } else {
            $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();
            $baru = Penugasan::create([
                'minggu_piket_id' => $minggu->id,
                'user_id' => $user->id,
                'tugas_id' => $tugas->id,
                'jenis' => $jenis,
                'tanggal' => $tanggal->toDateString(),
                'tenggat' => Tenggat::untuk($jenis, $tanggal),
                'status' => StatusPenugasan::Terjadwal,
            ] + $tandai);
        }

        LogAktivitas::catat('jadwal.ubah_petugas', $minggu, ['sesi' => $sesi->value, 'tugas' => $tugas->nama, 'user_id' => $user?->id]);

        // B8: setelah terbit, hanya warga yang terdampak yang diberi tahu.
        if ($terbit && $lama?->id !== $user?->id) {
            $this->beritahuPergantian($lama, $user, $baru, $tugas->nama, $sesi->label());
        }
    }

    /** Email B8 untuk petugas lama (dilepas) dan petugas baru. */
    private function beritahuPergantian(?User $lama, ?User $baru, ?Penugasan $baris, string $namaTugas, string $sesi): void
    {
        if ($lama) {
            $this->pemberitahuan->berubah($lama, "Piketmu berubah: {$namaTugas} dilepas", [
                "Pengurus mengubah jadwal piket {$sesi}. Kamu tidak lagi bertugas {$namaTugas}.",
            ]);
        }
        if ($baru && $baris) {
            $this->pemberitahuan->berubah($baru, "Piket baru untukmu: {$namaTugas}", [
                "Pengurus mengubah jadwal piket {$sesi}. Kamu sekarang bertugas:",
                '• '.$baris->load('tugas')->ringkasan(),
            ]);
        }
    }

    /** Mengisi atau mengosongkan satu hari piket harian (F4). */
    public function aturHarian(MingguPiket $minggu, CarbonImmutable $tanggal, ?User $user): void
    {
        $senin = $minggu->senin();
        if ($tanggal->lt($senin) || $tanggal->gt($senin->addDays(6))) {
            throw ValidationException::withMessages(['harian' => 'Tanggal di luar minggu ini.']);
        }

        // Baris Izin tidak memegang harinya lagi (R3), jadi hari itu bisa diisi pengganti.
        $baris = Penugasan::query()->bukanIzin()->where('jenis', 'harian')->whereDate('tanggal', $tanggal->toDateString())->with('user')->first();
        $lama = $baris?->user;
        if ($baris && $baris->status !== StatusPenugasan::Terjadwal) {
            throw ValidationException::withMessages(['harian' => 'Hari ini sudah terisi Bayar Bintang atau sudah dikerjakan.']);
        }
        // Pengganti boleh warga aktif mana pun (misalnya saat petugasnya izin, R3).
        if ($user && ! $user->aktif) {
            throw ValidationException::withMessages(['harian' => 'Warga ini tidak aktif.']);
        }

        $tenggat = Tenggat::untuk(JenisPenugasan::Harian, $tanggal);
        // Hari yang sudah lewat tidak perlu diberi tahu; yang akan datang diberi tahu di sini (B8).
        $beritahu = $tenggat->isFuture() && $lama?->id !== $user?->id;
        $tandai = $beritahu ? ['diberitahu_pada' => CarbonImmutable::now()] : [];

        if ($user === null) {
            $baris?->delete();
            $baru = null;
        } elseif ($baris) {
            $baris->update(['user_id' => $user->id] + $tandai);
            $baru = $baris;
        } else {
            $baru = Penugasan::create([
                'minggu_piket_id' => $minggu->id,
                'user_id' => $user->id,
                'jenis' => JenisPenugasan::Harian,
                'tanggal' => $tanggal->toDateString(),
                'tenggat' => $tenggat,
                'status' => StatusPenugasan::Terjadwal,
            ] + $tandai);
        }

        // Harian tidak punya tombol Terbitkan (R1): perubahan manual langsung terlihat warga.
        if (! $minggu->sudahTerbit('harian')) {
            $minggu->update(['status_harian' => StatusTerbit::Terbit]);
        }

        LogAktivitas::catat('jadwal.ubah_harian', $minggu, ['tanggal' => $tanggal->toDateString(), 'user_id' => $user?->id]);

        if ($beritahu) {
            $this->beritahuPergantian($lama, $user, $baru, 'piket harian '.$tanggal->translatedFormat('l, j M'), 'harian');
        }
    }

    public function pastikanDraf(MingguPiket $minggu, string $bagian): void
    {
        if ($minggu->sudahTerbit($bagian)) {
            throw ValidationException::withMessages(['jadwal' => 'Jadwal ini sudah diterbitkan, jadi daftar tugasnya tidak bisa diubah lagi.']);
        }
    }

    /**
     * R3: tugas Kamis/Minggu yang sudah terbit dan batasnya belum lewat, tetapi belum ada petugasnya
     * (misalnya tidak ada calon pengganti untuk warga yang izin). Ditampilkan paling atas di Pengawasan.
     *
     * @return Collection<int, array{minggu: MingguPiket, sesi: SesiTugas, tugas: Tugas, tanggal: CarbonImmutable}>
     */
    public function tanpaPetugas(MingguPiket $minggu, ?CarbonImmutable $sekarang = null): Collection
    {
        $sekarang ??= CarbonImmutable::now();
        $hasil = collect();

        foreach (SesiTugas::cases() as $sesi) {
            if (! $minggu->sudahTerbit($sesi) || ($sesi === SesiTugas::Minggu && $minggu->kerjaBakti())) {
                continue;
            }
            $jenis = $sesi === SesiTugas::Kamis ? JenisPenugasan::Kamis : JenisPenugasan::Minggu;
            $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();
            if (Tenggat::untuk($jenis, $tanggal)->lessThanOrEqualTo($sekarang)) {
                continue;
            }

            $terisi = $minggu->penugasan()->bukanIzin()->where('jenis', $jenis->value)->pluck('tugas_id')->all();
            foreach ($this->tugasAktif($minggu, $sesi) as $t) {
                if (! in_array($t->id, $terisi, true)) {
                    $hasil->push(['minggu' => $minggu, 'sesi' => $sesi, 'tugas' => $t, 'tanggal' => $tanggal]);
                }
            }
        }

        return $hasil;
    }

    /** Geng-geng non-harian minggu ini (pool Kamis). */
    public function gengKamis(MingguPiket $minggu): Collection
    {
        return Geng::query()->where('id', '!=', $minggu->geng_harian_id)->orderBy('urutan')->get();
    }
}
