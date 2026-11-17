<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
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

    /**
     * Tanggal kosong (F8): mulai besok, di minggu yang jadwal hariannya sudah ada, tanpa petugas harian
     * (hari yang kosong karena izin juga dihitung kosong), dan belum diajukan atau diterima untuk orang lain.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function tanggalKosong(int $batas = 8, ?int $kecualiPengajuan = null): Collection
    {
        $besok = CarbonImmutable::now()->addDay()->startOfDay();

        $minggu = MingguPiket::query()
            ->where('status_harian', 'terbit')
            ->whereDate('tanggal_senin', '>=', RotasiGeng::seninDari($besok)->toDateString())
            ->orderBy('tanggal_senin')
            ->get();

        if ($minggu->isEmpty()) {
            return collect();
        }

        $terisi = Penugasan::query()->bukanIzin()->where('jenis', JenisPenugasan::Harian->value)
            ->whereDate('tanggal', '>=', $besok->toDateString())
            ->pluck('tanggal')->map(fn ($t) => $t->toDateString())->all();

        $diajukan = PengajuanGantiBintang::query()
            ->where('status', StatusPengajuan::Menunggu->value)
            ->when($kecualiPengajuan, fn ($q) => $q->whereKeyNot($kecualiPengajuan))
            ->whereDate('tanggal_diajukan', '>=', $besok->toDateString())
            ->pluck('tanggal_diajukan')->map(fn ($t) => $t->toDateString())->all();

        $hasil = collect();
        foreach ($minggu as $m) {
            for ($i = 0; $i < 7; $i++) {
                $t = $m->senin()->addDays($i);
                if ($t->lt($besok) || in_array($t->toDateString(), $terisi, true) || in_array($t->toDateString(), $diajukan, true)) {
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

    public function tanggalMasihKosong(CarbonImmutable $tanggal, ?int $kecualiPengajuan = null): bool
    {
        return $this->tanggalKosong(PHP_INT_MAX, $kecualiPengajuan)
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
