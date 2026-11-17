<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Livewire\Pengurus\Pengawasan;
use App\Models\LogAktivitas;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Services\PelaksanaanPiket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * PRD bagian 15 R5: koreksi status oleh pengurus setelah tenggat, dengan catatan wajib.
 */
class KoreksiStatusTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
    }

    private function koreksi(Penugasan $p, StatusPenugasan $ke, string $catatan = 'Sudah dikerjakan, lupa ditandai'): Penugasan
    {
        return app(PelaksanaanPiket::class)->koreksi($p, $ke, $catatan, auth()->user());
    }

    public function test_bintang_menjadi_selesai_dengan_catatan_dan_tercatat_di_log(): void
    {
        $this->masukSebagaiPengurus();
        $u = $this->warga('A', 1);
        $p = $this->piket($this->mingguTerbit(), $u, JenisPenugasan::Harian, '2026-11-18', StatusPenugasan::Bintang);
        $this->waktu('2026-11-18 12:00');

        $this->koreksi($p, StatusPenugasan::Selesai);

        $p->refresh();
        $this->assertSame(StatusPenugasan::Selesai, $p->status);
        $this->assertSame('Sudah dikerjakan, lupa ditandai', $p->catatan);
        $this->assertSame(0, Penugasan::query()->bintangBelumLunas()->count());
        $log = LogAktivitas::query()->where('aksi', 'piket.koreksi')->firstOrFail();
        $this->assertSame(['dari' => 'bintang', 'ke' => 'selesai', 'catatan' => 'Sudah dikerjakan, lupa ditandai'], $log->data);
    }

    public function test_catatan_wajib_dan_hanya_setelah_tenggat(): void
    {
        $this->masukSebagaiPengurus();
        $p = $this->piket($this->mingguTerbit(), $this->warga('A', 1), JenisPenugasan::Harian, '2026-11-18');

        $this->waktu('2026-11-18 08:00');
        try {
            $this->koreksi($p, StatusPenugasan::Selesai);
            $this->fail('Sebelum tenggat seharusnya ditolak');
        } catch (ValidationException) {
        }

        $this->waktu('2026-11-18 10:00');
        $this->expectException(ValidationException::class);
        $this->koreksi($p, StatusPenugasan::Selesai, '  ');
    }

    public function test_bayar_bintang_dikoreksi_selesai_melunasi_bintang_asli(): void
    {
        $this->masukSebagaiPengurus();
        $gita = $this->warga('Gita');
        $asli = $this->piket($this->mingguTerbit('2026-11-09'), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'Pelataran');
        $bayar = $this->piket($this->mingguTerbit(), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'Pelataran', membayar: $asli);
        $this->waktu('2026-11-21 08:00');

        $this->koreksi($bayar, StatusPenugasan::Selesai);
        $this->assertSame(StatusPenugasan::Lunas, $asli->fresh()->status);

        // Dikoreksi balik: bintang asli terbuka lagi.
        $this->koreksi($bayar->fresh(), StatusPenugasan::Bintang, 'Ternyata belum dikerjakan');
        $this->assertSame(StatusPenugasan::Bintang, $asli->fresh()->status);
    }

    public function test_bintang_asli_dikoreksi_izin_melepas_pembayaran_dan_pengajuan(): void
    {
        $pengurus = $this->masukSebagaiPengurus();
        $u = $this->warga('A', 1);
        $asli = $this->piket($this->mingguTerbit('2026-11-09'), $u, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'WC 1');
        $bayar = $this->piket($this->mingguTerbit(), $u, JenisPenugasan::Kamis, status: StatusPenugasan::BayarBintang, tugas: 'WC 1', membayar: $asli);
        $harian = $this->piket($this->mingguTerbit('2026-11-02'), $u, JenisPenugasan::Harian, '2026-11-04', StatusPenugasan::Bintang);
        $ajuan = PengajuanGantiBintang::create(['penugasan_bintang_id' => $harian->id, 'user_id' => $u->id, 'tanggal_diajukan' => '2026-11-21', 'status' => 'menunggu']);
        $this->waktu('2026-11-18 10:00');

        $this->koreksi($asli, StatusPenugasan::Izin, 'Ternyata sedang pulang');
        $this->assertSame(StatusPenugasan::Terjadwal, $bayar->fresh()->status, 'Bayar Bintang yang berjalan menjadi piket biasa');
        $this->assertNull($bayar->fresh()->membayar_penugasan_id);

        $this->koreksi($harian, StatusPenugasan::Selesai);
        $this->assertSame(StatusPengajuan::Ditolak, $ajuan->fresh()->status);
        $this->assertSame($pengurus->id, $ajuan->fresh()->diputuskan_oleh_id);
    }

    public function test_tombol_koreksi_di_pengawasan(): void
    {
        $this->masukSebagaiPengurus();
        $p = $this->piket($this->mingguTerbit(), $this->warga('A', 1), JenisPenugasan::Harian, '2026-11-18');
        $this->waktu('2026-11-18 10:00');

        Livewire::test(Pengawasan::class)
            ->assertSee('Koreksi')
            ->call('koreksi', $p->id, 'selesai', '')
            ->assertDispatched('toast', jenis: 'gagal')
            ->call('koreksi', $p->id, 'selesai', 'Sudah diperiksa jam 08.30')
            ->assertDispatched('toast', jenis: 'sukses');

        $this->assertSame(StatusPenugasan::Selesai, $p->fresh()->status);
    }
}
