<?php

namespace App\Services;

use App\Models\Geng;
use App\Models\Pengaturan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Aturan B1: rotasi geng mingguan.
 *
 * geng_harian(minggu) = ((geng_acuan - 1 + selisih_minggu_dari_acuan) mod 3) + 1
 *
 * Nilai yang dihitung adalah *urutan* geng (1–3), bukan id.
 * Minggu piket dihitung Senin sampai Minggu.
 */
class RotasiGeng
{
    public const JUMLAH_GENG = 3;

    private CarbonImmutable $seninAcuan;

    public function __construct(CarbonInterface $mingguAcuan, private int $gengAcuan)
    {
        if ($gengAcuan < 1 || $gengAcuan > self::JUMLAH_GENG) {
            throw new \InvalidArgumentException('Geng acuan harus antara 1 dan '.self::JUMLAH_GENG.'.');
        }

        $this->seninAcuan = self::seninDari($mingguAcuan);
    }

    public static function dariPengaturan(): self
    {
        return new self(
            CarbonImmutable::parse(Pengaturan::ambil('minggu_acuan'), config('app.timezone')),
            (int) Pengaturan::ambil('geng_acuan'),
        );
    }

    /** Senin (jam 00.00 WITA) dari minggu yang memuat tanggal tersebut. */
    public static function seninDari(CarbonInterface $tanggal): CarbonImmutable
    {
        return CarbonImmutable::instance($tanggal)
            ->setTimezone(config('app.timezone'))
            ->startOfWeek(CarbonInterface::MONDAY)
            ->startOfDay();
    }

    public function selisihMinggu(CarbonInterface $tanggal): int
    {
        $senin = self::seninDari($tanggal);

        // Dua-duanya Senin 00.00 di zona yang sama, jadi selisih hari selalu kelipatan 7.
        return (int) round($this->seninAcuan->diffInDays($senin, false) / 7);
    }

    /** Urutan geng (1–3) yang piket harian pada minggu yang memuat tanggal tersebut. */
    public function urutanGengHarian(CarbonInterface $tanggal): int
    {
        $n = self::JUMLAH_GENG;
        $hasil = ($this->gengAcuan - 1 + $this->selisihMinggu($tanggal)) % $n;

        // Modulo PHP bisa negatif untuk minggu sebelum acuan.
        return (($hasil + $n) % $n) + 1;
    }

    /** @return list<int> Urutan 2 geng yang menjadi pool piket Kamis. */
    public function urutanGengKamis(CarbonInterface $tanggal): array
    {
        $harian = $this->urutanGengHarian($tanggal);

        return array_values(array_filter(range(1, self::JUMLAH_GENG), fn (int $u) => $u !== $harian));
    }

    public function gengHarian(CarbonInterface $tanggal): ?Geng
    {
        return Geng::query()->where('urutan', $this->urutanGengHarian($tanggal))->first();
    }

    /** @return Collection<int, Geng> */
    public function gengKamis(CarbonInterface $tanggal): Collection
    {
        return Geng::query()->whereIn('urutan', $this->urutanGengKamis($tanggal))->orderBy('urutan')->get();
    }

    /**
     * Pratinjau giliran beberapa minggu ke depan (F2).
     *
     * @return list<array{senin: CarbonImmutable, minggu: CarbonImmutable, geng_harian: int, geng_kamis: list<int>}>
     */
    public function pratinjau(int $jumlahMinggu = 8, ?CarbonInterface $mulai = null): array
    {
        $senin = self::seninDari($mulai ?? CarbonImmutable::now());
        $hasil = [];

        for ($i = 0; $i < $jumlahMinggu; $i++) {
            $s = $senin->addWeeks($i);
            $hasil[] = [
                'senin' => $s,
                'minggu' => $s->addDays(6),
                'geng_harian' => $this->urutanGengHarian($s),
                'geng_kamis' => $this->urutanGengKamis($s),
            ];
        }

        return $hasil;
    }
}
