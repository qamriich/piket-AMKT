<?php

namespace Tests\Unit;

use App\Services\RotasiGeng;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Aturan B1: geng_harian(minggu) = ((geng_acuan - 1 + selisih_minggu_dari_acuan) mod 3) + 1
 */
class RotasiGengTest extends TestCase
{
    private function tanggal(string $s): CarbonImmutable
    {
        return CarbonImmutable::parse($s, 'Asia/Makassar');
    }

    public function test_rotasi_benar_untuk_12_minggu_berturut_turut_dari_acuan(): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-16'), 1);

        $harapan = [1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3];
        foreach ($harapan as $i => $geng) {
            $senin = $this->tanggal('2026-11-16')->addWeeks($i);
            $this->assertSame($geng, $rotasi->urutanGengHarian($senin), "Minggu ke-{$i} ({$senin->toDateString()})");
        }
    }

    #[DataProvider('gengAcuanProvider')]
    public function test_rotasi_mengikuti_geng_acuan(int $gengAcuan, array $harapan): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-16'), $gengAcuan);

        foreach ($harapan as $i => $geng) {
            $this->assertSame($geng, $rotasi->urutanGengHarian($this->tanggal('2026-11-16')->addWeeks($i)));
        }
    }

    public static function gengAcuanProvider(): array
    {
        return [
            'acuan geng 2' => [2, [2, 3, 1, 2, 3, 1]],
            'acuan geng 3' => [3, [3, 1, 2, 3, 1, 2]],
        ];
    }

    public function test_semua_hari_dalam_satu_minggu_senin_sampai_minggu_memakai_geng_yang_sama(): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-16'), 1);

        // 23–29 Nov 2026 = minggu ke-1 = Geng 2.
        foreach (['2026-11-23', '2026-11-25', '2026-11-28', '2026-11-29 23:59'] as $t) {
            $this->assertSame(2, $rotasi->urutanGengHarian($this->tanggal($t)), $t);
        }
        // Senin berikutnya sudah ganti.
        $this->assertSame(3, $rotasi->urutanGengHarian($this->tanggal('2026-11-30 00:00')));
    }

    public function test_minggu_sebelum_acuan_tetap_benar(): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-16'), 1);

        $this->assertSame(3, $rotasi->urutanGengHarian($this->tanggal('2026-11-09')));
        $this->assertSame(2, $rotasi->urutanGengHarian($this->tanggal('2026-11-02')));
        $this->assertSame(1, $rotasi->urutanGengHarian($this->tanggal('2026-10-26')));
    }

    public function test_tidak_terikat_bulan_atau_tahun(): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-16'), 1);

        // 28 Des 2026 = +6 minggu; 4 Jan 2027 = +7 minggu.
        $this->assertSame(1, $rotasi->urutanGengHarian($this->tanggal('2026-12-28')));
        $this->assertSame(2, $rotasi->urutanGengHarian($this->tanggal('2027-01-04')));
        // 52 minggu kemudian: 52 mod 3 = 1.
        $this->assertSame(2, $rotasi->urutanGengHarian($this->tanggal('2026-11-16')->addWeeks(52)));
    }

    public function test_minggu_acuan_bukan_senin_dinormalkan_ke_senin(): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-19'), 1); // Kamis

        $this->assertSame(1, $rotasi->urutanGengHarian($this->tanggal('2026-11-16')));
        $this->assertSame(2, $rotasi->urutanGengHarian($this->tanggal('2026-11-23')));
    }

    public function test_pool_kamis_adalah_dua_geng_non_harian(): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-16'), 1);

        $this->assertSame([2, 3], $rotasi->urutanGengKamis($this->tanggal('2026-11-19')));
        $this->assertSame([1, 3], $rotasi->urutanGengKamis($this->tanggal('2026-11-26')));
        $this->assertSame([1, 2], $rotasi->urutanGengKamis($this->tanggal('2026-12-03')));
    }

    public function test_pratinjau_8_minggu_dimulai_dari_minggu_yang_memuat_tanggal_mulai(): void
    {
        $rotasi = new RotasiGeng($this->tanggal('2026-11-16'), 1);

        $p = $rotasi->pratinjau(8, $this->tanggal('2026-11-18'));

        $this->assertCount(8, $p);
        $this->assertSame('2026-11-16', $p[0]['senin']->toDateString());
        $this->assertSame('2026-11-22', $p[0]['minggu']->toDateString());
        $this->assertSame('2027-01-04', $p[7]['senin']->toDateString());
        $this->assertSame([1, 2, 3, 1, 2, 3, 1, 2], array_column($p, 'geng_harian'));
    }

    public function test_geng_acuan_di_luar_1_sampai_3_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RotasiGeng($this->tanggal('2026-11-16'), 4);
    }
}
