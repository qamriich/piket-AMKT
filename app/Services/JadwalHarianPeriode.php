<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPenugasan;
use App\Enums\StatusTerbit;
use App\Models\Geng;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\PeriodeHarian;
use App\Models\PeriodeHarianHari;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PRD bagian 15 R1: jadwal harian per periode.
 *
 * - Pengurus menyusun sekali per periode: untuk tiap geng, tiap anggota punya satu hari tetap.
 * - Tiap minggu, penugasan harian dibuat otomatis dari periode untuk geng yang sedang bertugas.
 *   Tidak ada tombol Terbitkan: begitu dibuat, langsung terlihat warga (status_harian = terbit).
 * - Perubahan jadwal periode berlaku mulai minggu berikutnya. Minggu ini tidak diubah, kecuali
 *   penugasan harian minggu ini memang belum pernah dibuat.
 */
class JadwalHarianPeriode
{
    public function aktifPada(CarbonImmutable $tanggal): ?PeriodeHarian
    {
        return PeriodeHarian::query()->mencakup($tanggal)->first();
    }

    /** Membuat atau mengubah nama dan rentang tanggal periode. Rentang periode tidak boleh tumpang tindih. */
    public function simpanPeriode(?PeriodeHarian $periode, string $nama, CarbonImmutable $mulai, CarbonImmutable $selesai): PeriodeHarian
    {
        if ($selesai->lt($mulai)) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.']);
        }

        $bentrok = PeriodeHarian::query()
            ->when($periode, fn ($q) => $q->whereKeyNot($periode->id))
            ->whereDate('tanggal_mulai', '<=', $selesai->toDateString())
            ->whereDate('tanggal_selesai', '>=', $mulai->toDateString())
            ->first();
        if ($bentrok) {
            throw ValidationException::withMessages(['tanggal_mulai' => "Rentang tanggal bentrok dengan periode \"{$bentrok->nama}\"."]);
        }

        $baru = $periode === null;
        $periode ??= new PeriodeHarian;
        $periode->fill(['nama' => $nama, 'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString()]);
        $berubah = $periode->getDirty();
        $periode->save();

        if ($berubah) {
            LogAktivitas::catat($baru ? 'periode_harian.dibuat' : 'periode_harian.diubah', $periode, $berubah);
            if (! $baru) {
                $this->berlakukanUlang($periode);
            }
        }

        return $periode;
    }

    /**
     * Menyimpan pembagian hari periode.
     *
     * @param  array<int|string, array<int|string, int|string|null>>  $peta  geng_id => [hari 1–7 => user_id|null]
     */
    public function simpanHari(PeriodeHarian $periode, array $peta): void
    {
        $gengs = Geng::query()->pluck('id')->all();
        $baris = [];
        $dipakai = [];

        foreach ($peta as $gengId => $hari) {
            $gengId = (int) $gengId;
            if (! in_array($gengId, $gengs, true)) {
                throw ValidationException::withMessages(['peta' => 'Geng tidak dikenal.']);
            }
            foreach ($hari as $h => $userId) {
                $h = (int) $h;
                if ($userId === null || $userId === '' || $userId === 0 || $userId === '0') {
                    continue;
                }
                if ($h < 1 || $h > 7) {
                    throw ValidationException::withMessages(['peta' => 'Hari tidak valid.']);
                }
                $user = User::query()->find((int) $userId);
                if (! $user || ! $user->aktif || $user->geng_id !== $gengId) {
                    throw ValidationException::withMessages(['peta' => 'Pilih anggota aktif dari geng itu sendiri.']);
                }
                if (isset($dipakai[$user->id])) {
                    throw ValidationException::withMessages(['peta' => "{$user->nama} dipilih di lebih dari satu hari. Satu orang hanya satu hari."]);
                }
                $dipakai[$user->id] = true;
                $baris[] = ['geng_id' => $gengId, 'hari' => $h, 'user_id' => $user->id];
            }
        }

        DB::transaction(function () use ($periode, $baris) {
            $periode->hari()->delete();
            foreach ($baris as $b) {
                PeriodeHarianHari::create(array_merge($b, ['periode_harian_id' => $periode->id]));
            }
        });

        LogAktivitas::catat('periode_harian.jadwal_disimpan', $periode, ['jumlah' => count($baris)]);
        $this->berlakukanUlang($periode);
    }

    /**
     * Membuat penugasan harian minggu ini dari periode, untuk geng yang sedang bertugas.
     * Hanya sekali per minggu (ditandai status_harian = terbit), supaya hari yang sengaja
     * dikosongkan atau diganti pengurus tidak terisi lagi. Mengembalikan jumlah penugasan baru.
     */
    public function terapkan(MingguPiket $minggu): int
    {
        if ($minggu->status_harian === StatusTerbit::Terbit) {
            return 0;
        }

        $dibuat = 0;
        $tercakup = false;

        DB::transaction(function () use ($minggu, &$dibuat, &$tercakup) {
            $periodeCache = [];
            for ($i = 0; $i < 7; $i++) {
                $tanggal = $minggu->senin()->addDays($i);
                $periode = $periodeCache[$tanggal->toDateString()] ??= $this->aktifPada($tanggal);
                if (! $periode) {
                    continue;
                }
                $tercakup = true;

                $baris = $periode->hari()
                    ->where('geng_id', $minggu->geng_harian_id)
                    ->where('hari', $tanggal->dayOfWeekIso)
                    ->with('user')
                    ->first();
                $user = $baris?->user;

                // Anggota yang sudah pindah geng atau nonaktif dilewati; harinya kosong sampai periode diperbarui.
                if (! $user || ! $user->aktif || $user->geng_id !== $minggu->geng_harian_id) {
                    continue;
                }
                if (Penugasan::query()->where('jenis', JenisPenugasan::Harian->value)->whereDate('tanggal', $tanggal->toDateString())->exists()) {
                    continue;
                }

                // R3: warga yang izin pulang hari itu tercatat Izin, dan harinya menjadi kosong.
                Penugasan::create([
                    'minggu_piket_id' => $minggu->id,
                    'user_id' => $user->id,
                    'jenis' => JenisPenugasan::Harian,
                    'tanggal' => $tanggal->toDateString(),
                    'tenggat' => Tenggat::untuk(JenisPenugasan::Harian, $tanggal),
                    'status' => $user->sedangIzinPada($tanggal) ? StatusPenugasan::Izin : StatusPenugasan::Terjadwal,
                ]);
                $dibuat++;
            }

            if ($tercakup) {
                $minggu->update(['status_harian' => StatusTerbit::Terbit]);
            }
        });

        return $dibuat;
    }

    /**
     * Setelah periode diubah: minggu berikutnya dan seterusnya (yang sudah punya baris minggu)
     * dibuat ulang dari periode terbaru. Minggu ini hanya diterapkan kalau belum pernah.
     */
    public function berlakukanUlang(PeriodeHarian $periode): void
    {
        $seninDepan = RotasiGeng::seninDari(CarbonImmutable::now())->addWeek();

        $mingguDepan = MingguPiket::query()
            ->whereDate('tanggal_senin', '>=', $seninDepan->toDateString())
            ->get();

        foreach ($mingguDepan as $m) {
            DB::transaction(function () use ($m) {
                $m->penugasan()
                    ->where('jenis', JenisPenugasan::Harian->value)
                    ->whereIn('status', [StatusPenugasan::Terjadwal->value, StatusPenugasan::Izin->value])
                    ->delete();
                $m->update(['status_harian' => StatusTerbit::Draf]);
            });
            $this->terapkan($m->fresh());
        }

        $mingguIni = MingguPiket::query()
            ->whereDate('tanggal_senin', RotasiGeng::seninDari(CarbonImmutable::now())->toDateString())
            ->first();
        if ($mingguIni) {
            $this->terapkan($mingguIni);
        }
    }

    /** @return array<int, array<int, int|null>> geng_id => [hari => user_id|null] dari periode tersimpan */
    public function peta(PeriodeHarian $periode): array
    {
        $peta = [];
        foreach (Geng::query()->orderBy('urutan')->pluck('id') as $gengId) {
            $peta[$gengId] = array_fill(1, 7, null);
        }
        foreach ($periode->hari as $b) {
            $peta[$b->geng_id][$b->hari] = $b->user_id;
        }

        return $peta;
    }
}
