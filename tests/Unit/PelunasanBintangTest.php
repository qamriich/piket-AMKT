<?php

namespace Tests\Unit;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPenugasan;
use App\Models\LogAktivitas;
use App\Models\Penugasan;
use App\Services\BintangOtomatis;
use App\Services\PelaksanaanPiket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * F6 (tandai selesai dan pemeriksaan) dan B5 (pelunasan bintang).
 */
class PelunasanBintangTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
    }

    private function layanan(): PelaksanaanPiket
    {
        return app(PelaksanaanPiket::class);
    }

    public function test_bayar_bintang_selesai_membuat_bintang_asli_lunas(): void
    {
        $gita = $this->warga('Gita');
        $asli = $this->piket($this->mingguTerbit('2026-11-09'), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'Pelataran');
        $bayar = $this->piket($this->mingguTerbit(), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::BayarBintang, tugas: 'Pelataran', membayar: $asli);

        $this->waktu('2026-11-19 16:00');
        $this->layanan()->tandaiSelesai($bayar, $gita);

        $this->assertSame(StatusPenugasan::Selesai, $bayar->fresh()->status);
        $this->assertSame(StatusPenugasan::Lunas, $asli->fresh()->status);
        $this->assertSame(0, Penugasan::query()->bintangBelumLunas()->count());
        $this->assertSame($gita->id, $bayar->fresh()->selesai_oleh_id);
        $this->assertNotNull($bayar->fresh()->selesai_pada);
    }

    public function test_batalkan_selesai_mengembalikan_bintang_asli(): void
    {
        $gita = $this->warga('Gita');
        $pengurus = $this->masukSebagaiPengurus();
        $asli = $this->piket($this->mingguTerbit('2026-11-09'), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'Pelataran');
        $bayar = $this->piket($this->mingguTerbit(), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::BayarBintang, tugas: 'Pelataran', membayar: $asli);

        $this->waktu('2026-11-19 16:00');
        $this->layanan()->tandaiSelesai($bayar, $gita);
        $this->layanan()->batalkanSelesai($bayar->fresh(), $pengurus);

        $this->assertSame(StatusPenugasan::BayarBintang, $bayar->fresh()->status);
        $this->assertNull($bayar->fresh()->selesai_pada);
        $this->assertSame(StatusPenugasan::Bintang, $asli->fresh()->status);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'piket.batal_selesai')->exists());
    }

    public function test_tanda_selesai_setelah_tenggat_tetap_bintang(): void
    {
        $u = $this->warga('A');
        $p = $this->piket($this->mingguTerbit(), $u, JenisPenugasan::Harian, '2026-11-18');

        $this->waktu('2026-11-18 09:10');
        try {
            $this->layanan()->tandaiSelesai($p, $u);
            $this->fail('Seharusnya ditolak');
        } catch (ValidationException) {
        }

        // Pengurus pun tidak bisa memakai tombol biasa; harus lewat Koreksi (R5).
        $this->expectException(ValidationException::class);
        try {
            $this->layanan()->tandaiSelesai($p, $this->masukSebagaiPengurus());
        } finally {
            app(BintangOtomatis::class)->proses();
            $this->assertSame(StatusPenugasan::Bintang, $p->fresh()->status);
        }
    }

    public function test_warga_wajib_diperiksa_tidak_bisa_menandai_sendiri_tapi_pengurus_bisa(): void
    {
        $nivia = $this->warga('Nivia', atribut: ['wajib_diperiksa' => true]);
        $p = $this->piket($this->mingguTerbit(), $nivia, JenisPenugasan::Harian, '2026-11-18');
        $this->waktu('2026-11-18 06:00');

        $this->assertFalse($this->layanan()->bisaDitandaiSendiri($p, $nivia));
        try {
            $this->layanan()->tandaiSelesai($p, $nivia);
            $this->fail('Seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('pengurus', $e->getMessage());
        }

        $wilda = $this->masukSebagaiPengurus();
        $this->layanan()->tandaiSelesai($p, $wilda);

        // B7: tercatat siapa yang menandai dan kapan.
        $this->assertSame(StatusPenugasan::Selesai, $p->fresh()->status);
        $this->assertSame($wilda->id, $p->fresh()->selesai_oleh_id);
        $this->assertSame('2026-11-18 06:00', $p->fresh()->selesai_pada->format('Y-m-d H:i'));
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'piket.selesai')->where('user_id', $wilda->id)->exists());
    }

    public function test_warga_tidak_bisa_menandai_piket_orang_lain_atau_sebelum_harinya(): void
    {
        $a = $this->warga('A');
        $b = $this->warga('B');
        $m = $this->mingguTerbit();
        $punyaB = $this->piket($m, $b, JenisPenugasan::Harian, '2026-11-18');
        $besok = $this->piket($m, $a, JenisPenugasan::Harian, '2026-11-19');
        $this->waktu('2026-11-18 06:00');

        foreach ([[$punyaB, $a], [$besok, $a]] as [$p, $oleh]) {
            try {
                $this->layanan()->tandaiSelesai($p, $oleh);
                $this->fail('Seharusnya ditolak');
            } catch (ValidationException) {
                $this->assertSame(StatusPenugasan::Terjadwal, $p->fresh()->status);
            }
        }
    }

    public function test_piket_draf_tidak_bisa_ditandai(): void
    {
        $u = $this->warga('A');
        $p = $this->piket($this->mingguTerbit(status: ['status_kamis' => 'draf']), $u, JenisPenugasan::Kamis, tugas: 'WC 1');
        $this->waktu('2026-11-19 10:00');

        $this->expectException(ValidationException::class);
        $this->layanan()->tandaiSelesai($p, $u);
    }

    public function test_catatan_pengurus_tersimpan(): void
    {
        $u = $this->warga('A');
        $p = $this->piket($this->mingguTerbit(), $u, JenisPenugasan::Harian, '2026-11-18');

        $this->layanan()->simpanCatatan($p, '  Lantai dapur masih basah  ', $this->masukSebagaiPengurus());
        $this->assertSame('Lantai dapur masih basah', $p->fresh()->catatan);

        $this->layanan()->simpanCatatan($p, '', auth()->user());
        $this->assertNull($p->fresh()->catatan);

        $this->expectException(ValidationException::class);
        $this->layanan()->simpanCatatan($p, 'x', $u); // warga biasa tidak boleh
    }
}
