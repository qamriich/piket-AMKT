<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Models\PeriodeHarian;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F8 dan B5: bintang harian dilunasi lewat pengajuan ganti di tanggal kosong.
 */
class GantiBintang
{
    public function __construct(private JadwalMinggu $jadwal, private Pemberitahuan $pemberitahuan) {}

    /** Berapa minggu ke depan hari kosong dicari (R6: "minggu mana pun ke depan"). */
    public const MINGGU_KE_DEPAN = 8;

    /**
     * Tanggal kosong (F8, R6): mulai besok sampai 8 minggu ke depan, hari tanpa petugas harian, dan belum
     * diajukan orang lain. Hari yang kosong karena izin juga dihitung kosong.
     *
     * - Minggu yang jadwal hariannya sudah dibuat: dilihat dari penugasan yang ada.
     * - Minggu depan yang belum dibuat: dihitung dari jadwal harian periode (R1) dan rotasi geng (B1).
     *   Hari di luar periode mana pun tidak ditampilkan, karena jadwal hariannya belum ada.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function tanggalKosong(int $batas = PHP_INT_MAX, ?int $kecualiPengajuan = null): Collection
    {
        $besok = CarbonImmutable::now()->addDay()->startOfDay();
        $seninAwal = RotasiGeng::seninDari($besok);
        $akhir = $seninAwal->addWeeks(self::MINGGU_KE_DEPAN)->subDay();

        $mingguAda = MingguPiket::query()
            ->whereDate('tanggal_senin', '>=', $seninAwal->toDateString())
            ->whereDate('tanggal_senin', '<=', $akhir->toDateString())
            ->get()
            ->keyBy(fn (MingguPiket $m) => $m->tanggal_senin->toDateString());

        $terisi = Penugasan::query()->bukanIzin()->where('jenis', JenisPenugasan::Harian->value)
            ->whereDate('tanggal', '>=', $besok->toDateString())
            ->whereDate('tanggal', '<=', $akhir->toDateString())
            ->pluck('tanggal')->map(fn ($t) => $t->toDateString())->flip();

        $diajukan = PengajuanGantiBintang::query()
            ->where('status', StatusPengajuan::Menunggu->value)
            ->when($kecualiPengajuan, fn ($q) => $q->whereKeyNot($kecualiPengajuan))
            ->whereDate('tanggal_diajukan', '>=', $besok->toDateString())
            ->pluck('tanggal_diajukan')->map(fn ($t) => $t->toDateString())->flip();

        $rotasi = RotasiGeng::dariPengaturan();
        $periode = PeriodeHarian::query()
            ->whereDate('tanggal_mulai', '<=', $akhir->toDateString())
            ->whereDate('tanggal_selesai', '>=', $besok->toDateString())
            ->with('hari.user')
            ->get();
        $hasil = collect();

        for ($w = 0; $w < self::MINGGU_KE_DEPAN; $w++) {
            $senin = $seninAwal->addWeeks($w);
            $m = $mingguAda->get($senin->toDateString());
            $sudahDibuat = $m && $m->sudahTerbit('harian');
            $gengId = $m?->geng_harian_id ?? $rotasi->gengHarian($senin)?->id;

            for ($i = 0; $i < 7; $i++) {
                $t = $senin->addDays($i);
                $kunci = $t->toDateString();
                if ($t->lt($besok) || $terisi->has($kunci) || $diajukan->has($kunci)) {
                    continue;
                }
                if (! $sudahDibuat && ! $this->kosongMenurutPeriode($periode, $t, $gengId)) {
                    continue;
                }

                $hasil->push($t);
                if ($hasil->count() >= $batas) {
                    return $hasil;
                }
            }
        }

        return $hasil;
    }

    /** Hari di minggu yang belum dibuat: kosong kalau ada periode tetapi tidak ada anggota geng yang bertugas di hari itu. */
    private function kosongMenurutPeriode(Collection $semuaPeriode, CarbonImmutable $tanggal, ?int $gengId): bool
    {
        $periode = $semuaPeriode->first(fn (PeriodeHarian $p) => $p->mencakup($tanggal));
        if (! $periode || ! $gengId) {
            return false;
        }

        $user = $periode->hari->first(fn ($b) => $b->geng_id === $gengId && $b->hari === $tanggal->dayOfWeekIso)?->user;

        // Sama seperti JadwalHarianPeriode::terapkan: anggota yang pindah geng, nonaktif, atau izin tidak mengisi harinya.
        return ! $user || ! $user->aktif || $user->geng_id !== $gengId || $user->izinUntuk(JenisPenugasan::Harian, $tanggal);
    }

    public function tanggalMasihKosong(CarbonImmutable $tanggal, ?int $kecualiPengajuan = null): bool
    {
        return $this->tanggalKosong(kecualiPengajuan: $kecualiPengajuan)
            ->contains(fn (CarbonImmutable $t) => $t->isSameDay($tanggal));
    }

    /** Warga mengajukan ganti satu bintang hariannya di tanggal kosong. */
    public function ajukan(User $user, Penugasan $bintang, CarbonImmutable $tanggal): PengajuanGantiBintang
    {
        if ($bintang->user_id !== $user->id) {
            throw ValidationException::withMessages(['pengajuan' => 'Bintang ini bukan milikmu.']);
        }
        if ($bintang->jenis !== JenisPenugasan::Harian) {
            throw ValidationException::withMessages(['pengajuan' => 'Hanya bintang harian yang diganti lewat pengajuan. Bintang Kamis dan Minggu dijadwalkan ulang otomatis.']);
        }
        if (! Penugasan::query()->bintangBelumLunas()->whereKey($bintang->id)->exists()) {
            throw ValidationException::withMessages(['pengajuan' => 'Bintang ini sudah lunas.']);
        }
        if ($bintang->pengajuanGantiBintang()->where('status', StatusPengajuan::Menunggu->value)->exists()) {
            throw ValidationException::withMessages(['pengajuan' => 'Bintang ini sudah diajukan dan masih menunggu jawaban pengurus.']);
        }
        if ($bintang->pembayaran()->where('status', StatusPenugasan::BayarBintang->value)->exists()) {
            throw ValidationException::withMessages(['pengajuan' => 'Bintang ini sudah dijadwalkan ganti.']);
        }

        $pengajuan = DB::transaction(function () use ($user, $bintang, $tanggal) {
            // Kunci baris bintang supaya dua klik bersamaan tidak membuat dua pengajuan.
            Penugasan::query()->lockForUpdate()->find($bintang->id);
            if (! $this->tanggalMasihKosong($tanggal)) {
                throw ValidationException::withMessages(['pengajuan' => 'Tanggal itu sudah tidak kosong. Pilih hari lain, ya.']);
            }

            return PengajuanGantiBintang::create([
                'penugasan_bintang_id' => $bintang->id,
                'user_id' => $user->id,
                'tanggal_diajukan' => $tanggal->toDateString(),
                'status' => StatusPengajuan::Menunggu,
            ]);
        });

        $this->pemberitahuan->pengajuanBaru($pengajuan);

        return $pengajuan;
    }

    /** Pengurus menerima: tanggal itu terisi piket harian Bayar Bintang untuk pengaju (F8, B5). */
    public function terima(PengajuanGantiBintang $a, User $oleh, ?string $catatan = null): Penugasan
    {
        $this->pastikanMenunggu($a, $oleh);
        $catatan = $this->rapikanCatatan($catatan);
        $tanggal = CarbonImmutable::parse($a->tanggal_diajukan->toDateString(), config('app.timezone'));

        try {
            $baru = DB::transaction(function () use ($a, $oleh, $catatan, $tanggal) {
                $bintang = Penugasan::query()->lockForUpdate()->findOrFail($a->penugasan_bintang_id);

                if (! Penugasan::query()->bintangBelumLunas()->whereKey($bintang->id)->exists()) {
                    throw ValidationException::withMessages(['pengajuan' => 'Bintangnya sudah lunas atau dikoreksi. Tolak pengajuan ini.']);
                }
                if ($bintang->pembayaran()->where('status', StatusPenugasan::BayarBintang->value)->exists()) {
                    throw ValidationException::withMessages(['pengajuan' => 'Bintang ini sudah dijadwalkan ganti di hari lain.']);
                }
                if (! $this->tanggalMasihKosong($tanggal, $a->id)) {
                    throw ValidationException::withMessages(['pengajuan' => 'Tanggal '.$tanggal->translatedFormat('j M').' sudah tidak kosong. Tolak pengajuan ini supaya warga memilih hari lain.']);
                }

                $minggu = $this->jadwal->siapkan($tanggal);
                $baru = Penugasan::create([
                    'minggu_piket_id' => $minggu->id,
                    'user_id' => $a->user_id,
                    'jenis' => JenisPenugasan::Harian,
                    'tanggal' => $tanggal->toDateString(),
                    'tenggat' => Tenggat::untuk(JenisPenugasan::Harian, $tanggal),
                    'status' => StatusPenugasan::BayarBintang,
                    'membayar_penugasan_id' => $bintang->id,
                    // Pengaju sudah mendapat email keputusan; tidak perlu email "piket harian minggu ini" lagi.
                    'diberitahu_pada' => CarbonImmutable::now(),
                ]);

                $a->update([
                    'status' => StatusPengajuan::Diterima,
                    'diputuskan_oleh_id' => $oleh->id,
                    'diputuskan_pada' => CarbonImmutable::now(),
                    'catatan' => $catatan,
                ]);

                return $baru;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['pengajuan' => 'Tanggal itu baru saja terisi. Tolak pengajuan ini supaya warga memilih hari lain.']);
        }

        LogAktivitas::catat('pengajuan.diterima', $a, ['penugasan_id' => $baru->id]);
        $this->pemberitahuan->pengajuanDiputuskan($a->refresh());

        return $baru;
    }

    public function tolak(PengajuanGantiBintang $a, User $oleh, ?string $catatan = null): void
    {
        $this->pastikanMenunggu($a, $oleh);

        $a->update([
            'status' => StatusPengajuan::Ditolak,
            'diputuskan_oleh_id' => $oleh->id,
            'diputuskan_pada' => CarbonImmutable::now(),
            'catatan' => $this->rapikanCatatan($catatan),
        ]);

        LogAktivitas::catat('pengajuan.ditolak', $a, ['catatan' => $a->catatan]);
        $this->pemberitahuan->pengajuanDiputuskan($a);
    }

    private function pastikanMenunggu(PengajuanGantiBintang $a, User $oleh): void
    {
        if (! $oleh->isPengurus()) {
            throw ValidationException::withMessages(['pengajuan' => 'Hanya pengurus yang bisa memutuskan pengajuan.']);
        }
        if ($a->status !== StatusPengajuan::Menunggu) {
            throw ValidationException::withMessages(['pengajuan' => 'Pengajuan ini sudah diputuskan.']);
        }
    }

    private function rapikanCatatan(?string $catatan): ?string
    {
        $catatan = trim((string) $catatan);
        if (mb_strlen($catatan) > 500) {
            throw ValidationException::withMessages(['catatan' => 'Catatan maksimal 500 karakter.']);
        }

        return $catatan === '' ? null : $catatan;
    }
}
