<?php

namespace Tests;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\StatusPenugasan;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\TugasAktif;
use App\Models\User;
use App\Services\Tenggat;
use Carbon\CarbonImmutable;

/**
 * Data contoh Tahap 3. Minggu acuan: Senin 16 Nov 2026 (Geng 1 harian, Geng 2 & 3 pool Kamis).
 * Kamis 19 Nov (batas Jumat 20 Nov 18.00), Minggu 22 Nov (batas Senin 23 Nov 18.00).
 */
trait BuatPiket
{
    /** @var array<string, Tugas> */
    protected array $tugas = [];

    protected function tugas(string $nama): Tugas
    {
        return $this->tugas[$nama] ??= Tugas::create(['nama' => $nama, 'jenis' => JenisTugas::KamisMinggu, 'urutan' => count($this->tugas) + 1]);
    }

    /** Minggu yang harian, Kamis, dan Minggu-nya sudah terbit (kecuali diminta lain). */
    protected function mingguTerbit(string $senin = '2026-11-16', array $status = [], int $gengHarian = 1, array $aktif = []): MingguPiket
    {
        $m = MingguPiket::create(array_merge([
            'tanggal_senin' => $senin,
            'geng_harian_id' => $this->geng($gengHarian)->id,
            'status_harian' => 'terbit',
            'status_kamis' => 'terbit',
            'status_minggu' => 'terbit',
        ], $status));

        foreach ($aktif as $sesi => $namaTugas) {
            foreach ($namaTugas as $n) {
                TugasAktif::create(['minggu_piket_id' => $m->id, 'tugas_id' => $this->tugas($n)->id, 'sesi' => $sesi]);
            }
        }

        return $m;
    }

    protected function warga(string $nama, int $geng = 2, array $atribut = []): User
    {
        return User::factory()->create(array_merge(['nama' => $nama, 'geng_id' => $this->geng($geng)->id], $atribut));
    }

    protected function piket(
        MingguPiket $m,
        User $u,
        JenisPenugasan $jenis,
        ?string $tanggal = null,
        StatusPenugasan $status = StatusPenugasan::Terjadwal,
        ?string $tugas = null,
        ?Penugasan $membayar = null,
    ): Penugasan {
        $tanggal ??= match ($jenis) {
            JenisPenugasan::Harian => $m->senin()->addDays(2)->toDateString(),
            JenisPenugasan::Kamis => $m->kamis()->toDateString(),
            default => $m->hariMinggu()->toDateString(),
        };
        $t = CarbonImmutable::parse($tanggal, config('app.timezone'));

        return Penugasan::create([
            'minggu_piket_id' => $m->id,
            'user_id' => $u->id,
            'tugas_id' => $tugas ? $this->tugas($tugas)->id : null,
            'jenis' => $jenis,
            'tanggal' => $tanggal,
            'tenggat' => Tenggat::untuk($jenis, $t),
            'status' => $status,
            'membayar_penugasan_id' => $membayar?->id,
        ]);
    }

    protected function waktu(string $kapan): void
    {
        $this->travelTo(CarbonImmutable::parse($kapan, config('app.timezone')));
    }
}
