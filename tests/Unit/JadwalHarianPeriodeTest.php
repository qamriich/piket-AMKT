<?php

namespace Tests\Unit;

use App\Enums\StatusPenugasan;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\PeriodeHarian;
use App\Models\User;
use App\Services\JadwalHarianPeriode;
use App\Services\JadwalMinggu;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PRD bagian 15 R1: jadwal harian per periode, dipakai otomatis tiap giliran geng.
 * Minggu acuan 16 Nov 2026 = Geng 1 harian, 23 Nov = Geng 2, 30 Nov = Geng 3.
 */
class JadwalHarianPeriodeTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array<int, User>> urutan geng => [hari => user] */
    private array $petugas = [];

    private PeriodeHarian $periode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00', 'Asia/Makassar')); // Rabu

        // Tiap geng: Senin, Rabu, Jumat terisi; hari lain kosong.
        foreach ([1, 2, 3] as $g) {
            foreach ([1, 3, 5] as $h) {
                $this->petugas[$g][$h] = User::factory()->create(['nama' => "G{$g}H{$h}", 'geng_id' => $this->geng($g)->id]);
            }
        }

        $this->periode = $this->layanan()->simpanPeriode(null, 'Semester Ganjil', $this->t('2026-11-16'), $this->t('2027-01-31'));
        $this->layanan()->simpanHari($this->periode, $this->peta());
    }

    private function layanan(): JadwalHarianPeriode
    {
        return app(JadwalHarianPeriode::class);
    }

    private function t(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal, 'Asia/Makassar');
    }

    private function peta(): array
    {
        $peta = [];
        foreach ($this->petugas as $g => $hari) {
            $peta[$this->geng($g)->id] = array_map(fn (User $u) => $u->id, $hari);
        }

        return $peta;
    }

    /** @return array<string, string> tanggal => nama petugas harian minggu itu */
    private function harianMinggu(string $senin): array
    {
        return Penugasan::query()->where('jenis', 'harian')
            ->whereDate('tanggal', '>=', $senin)->whereDate('tanggal', '<=', $this->t($senin)->addDays(6)->toDateString())
            ->with('user')->orderBy('tanggal')->get()
            ->mapWithKeys(fn (Penugasan $p) => [$p->tanggal->toDateString() => $p->user->nama])->all();
    }

    public function test_tiap_minggu_otomatis_dibuat_untuk_geng_yang_bertugas_tanpa_terbitkan(): void
    {
        $m1 = app(JadwalMinggu::class)->siapkan($this->t('2026-11-16'));
        $m2 = app(JadwalMinggu::class)->siapkan($this->t('2026-11-23'));
        $m3 = app(JadwalMinggu::class)->siapkan($this->t('2026-11-30'));

        $this->assertSame(['2026-11-16' => 'G1H1', '2026-11-18' => 'G1H3', '2026-11-20' => 'G1H5'], $this->harianMinggu('2026-11-16'));
        $this->assertSame(['2026-11-23' => 'G2H1', '2026-11-25' => 'G2H3', '2026-11-27' => 'G2H5'], $this->harianMinggu('2026-11-23'));
        $this->assertSame(['2026-11-30' => 'G3H1', '2026-12-02' => 'G3H3', '2026-12-04' => 'G3H5'], $this->harianMinggu('2026-11-30'));

        foreach ([$m1, $m2, $m3] as $m) {
            $this->assertTrue($m->fresh()->sudahTerbit('harian'), 'Harian langsung terlihat, tanpa tombol Terbitkan');
        }

        $p = Penugasan::query()->where('jenis', 'harian')->whereDate('tanggal', '2026-11-18')->firstOrFail();
        $this->assertSame('2026-11-18 09:00', $p->tenggat->format('Y-m-d H:i'));
        $this->assertSame(StatusPenugasan::Terjadwal, $p->status);
    }

    public function test_hanya_dibuat_sekali_per_minggu_dan_hari_yang_dikosongkan_tidak_terisi_lagi(): void
    {
        $m = app(JadwalMinggu::class)->siapkan($this->t('2026-11-16'));
        app(JadwalMinggu::class)->aturHarian($m, $this->t('2026-11-20'), null); // pengurus mengosongkan Jumat

        app(JadwalMinggu::class)->siapkan($this->t('2026-11-16'));
        $this->layanan()->terapkan($m->fresh());

        $this->assertSame(['2026-11-16' => 'G1H1', '2026-11-18' => 'G1H3'], $this->harianMinggu('2026-11-16'));
    }

    public function test_hari_di_luar_periode_tidak_dibuat(): void
    {
        $this->layanan()->simpanPeriode($this->periode, 'Semester Ganjil', $this->t('2026-11-16'), $this->t('2026-11-25'));

        app(JadwalMinggu::class)->siapkan($this->t('2026-11-23'));

        $this->assertSame(['2026-11-23' => 'G2H1', '2026-11-25' => 'G2H3'], $this->harianMinggu('2026-11-23'));
    }

    public function test_tanpa_periode_minggu_tetap_kosong_dan_belum_terlihat(): void
    {
        PeriodeHarian::query()->delete();

        $m = app(JadwalMinggu::class)->siapkan($this->t('2026-11-16'));

        $this->assertSame([], $this->harianMinggu('2026-11-16'));
        $this->assertFalse($m->fresh()->sudahTerbit('harian'));
    }

    public function test_anggota_yang_pindah_geng_atau_nonaktif_dilewati(): void
    {
        $this->petugas[1][1]->update(['geng_id' => $this->geng(2)->id]);
        $this->petugas[1][3]->update(['aktif' => false]);

        app(JadwalMinggu::class)->siapkan($this->t('2026-11-16'));

        $this->assertSame(['2026-11-20' => 'G1H5'], $this->harianMinggu('2026-11-16'));
    }

    public function test_perubahan_periode_berlaku_mulai_minggu_berikutnya(): void
    {
        app(JadwalMinggu::class)->siapkan($this->t('2026-11-16'));
        app(JadwalMinggu::class)->siapkan($this->t('2026-11-23'));
        app(JadwalMinggu::class)->siapkan($this->t('2026-12-07')); // Geng 1 lagi

        // Geng 1: G1H1 pindah ke Selasa, Senin kosong.
        $peta = $this->peta();
        $peta[$this->geng(1)->id] = [2 => $this->petugas[1][1]->id, 3 => $this->petugas[1][3]->id, 5 => $this->petugas[1][5]->id];
        $this->layanan()->simpanHari($this->periode, $peta);

        // Minggu ini tidak berubah.
        $this->assertSame(['2026-11-16' => 'G1H1', '2026-11-18' => 'G1H3', '2026-11-20' => 'G1H5'], $this->harianMinggu('2026-11-16'));
        // Giliran Geng 1 berikutnya memakai jadwal baru.
        $this->assertSame(['2026-12-08' => 'G1H1', '2026-12-09' => 'G1H3', '2026-12-11' => 'G1H5'], $this->harianMinggu('2026-12-07'));
        // Minggu lain (Geng 2) tetap sama.
        $this->assertSame(['2026-11-23' => 'G2H1', '2026-11-25' => 'G2H3', '2026-11-27' => 'G2H5'], $this->harianMinggu('2026-11-23'));
    }

    public function test_perubahan_tidak_menimpa_piket_yang_sudah_dikerjakan_atau_bayar_bintang(): void
    {
        $m = app(JadwalMinggu::class)->siapkan($this->t('2026-12-07'));
        Penugasan::query()->where('minggu_piket_id', $m->id)->whereDate('tanggal', '2026-12-07')->update(['status' => 'bayar_bintang']);

        $this->layanan()->simpanHari($this->periode, $this->peta());

        $this->assertSame('bayar_bintang', Penugasan::query()->whereDate('tanggal', '2026-12-07')->value('status')->value);
        $this->assertSame(1, Penugasan::query()->where('jenis', 'harian')->whereDate('tanggal', '2026-12-07')->count());
    }

    public function test_validasi_simpan_hari(): void
    {
        $peta = $this->peta();
        // Orang Geng 2 dimasukkan ke Geng 1.
        $peta[$this->geng(1)->id][7] = $this->petugas[2][1]->id;
        $this->assertGagal(fn () => $this->layanan()->simpanHari($this->periode, $peta), 'geng itu sendiri');

        // Satu orang dua hari.
        $peta = $this->peta();
        $peta[$this->geng(1)->id][7] = $this->petugas[1][1]->id;
        $this->assertGagal(fn () => $this->layanan()->simpanHari($this->periode, $peta), 'lebih dari satu hari');
    }

    public function test_periode_tidak_boleh_tumpang_tindih_dan_tanggal_harus_urut(): void
    {
        $this->assertGagal(fn () => $this->layanan()->simpanPeriode(null, 'Bentrok', $this->t('2027-01-01'), $this->t('2027-02-28')), 'bentrok');
        $this->assertGagal(fn () => $this->layanan()->simpanPeriode(null, 'Terbalik', $this->t('2027-03-10'), $this->t('2027-03-01')), 'setelah tanggal mulai');

        $genap = $this->layanan()->simpanPeriode(null, 'Semester Genap', $this->t('2027-02-01'), $this->t('2027-06-30'));
        $this->assertSame($genap->id, $this->layanan()->aktifPada($this->t('2027-03-01'))->id);
        $this->assertSame($this->periode->id, $this->layanan()->aktifPada($this->t('2026-12-01'))->id);
    }

    private function assertGagal(\Closure $aksi, string $pesan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya gagal validasi.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($pesan, collect($e->errors())->flatten()->join(' '));
        }
    }

    public function test_harian_tidak_bisa_diterbitkan_manual(): void
    {
        $m = MingguPiket::query()->firstOrCreate(['tanggal_senin' => '2026-11-16'], ['geng_harian_id' => $this->geng(1)->id]);

        $this->assertGagal(fn () => app(JadwalMinggu::class)->terbitkan($m, 'harian'), 'otomatis');
    }
}
