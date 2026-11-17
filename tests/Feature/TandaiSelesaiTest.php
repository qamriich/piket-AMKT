<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPenugasan;
use App\Livewire\Pengurus\Pengawasan;
use App\Livewire\Warga\Beranda;
use App\Livewire\Warga\Piket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * F6 di tampilan: tombol "Saya sudah selesai" warga, pemeriksaan pengurus, dan catatan.
 */
class TandaiSelesaiTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
        $this->waktu('2026-11-18 06:00');
    }

    public function test_warga_menandai_selesai_tanpa_reload(): void
    {
        $ping = $this->warga('Ping', 1);
        $p = $this->piket($this->mingguTerbit(), $ping, JenisPenugasan::Harian, '2026-11-18');
        $this->actingAs($ping);

        $this->get(route('warga.piket'))->assertSee('Saya sudah selesai')->assertDontSee('segera aktif');

        Livewire::test(Piket::class)
            ->call('selesai', $p->id)
            ->assertDispatched('toast', pesan: 'Mantap, piketmu tercatat selesai!', jenis: 'sukses')
            ->assertSee('Mantap, sudah tercatat!')
            ->assertDontSee('Saya sudah selesai');

        $this->assertSame(StatusPenugasan::Selesai, $p->fresh()->status);
    }

    public function test_tombol_selesai_juga_ada_di_beranda(): void
    {
        $ping = $this->warga('Ping', 1);
        $p = $this->piket($this->mingguTerbit(), $ping, JenisPenugasan::Harian, '2026-11-18');
        $this->actingAs($ping);

        Livewire::test(Beranda::class)->assertSee('Sudah selesai')->call('selesai', $p->id)->assertDispatched('toast');
        $this->assertSame(StatusPenugasan::Selesai, $p->fresh()->status);
    }

    public function test_warga_tidak_bisa_menandai_piket_orang_lain_lewat_livewire(): void
    {
        $a = $this->warga('A', 1);
        $p = $this->piket($this->mingguTerbit(), $this->warga('B', 1), JenisPenugasan::Harian, '2026-11-18');

        $this->actingAs($a);
        Livewire::test(Piket::class)->call('selesai', $p->id)->assertDispatched('toast', jenis: 'gagal');
        $this->assertSame(StatusPenugasan::Terjadwal, $p->fresh()->status);
    }

    public function test_warga_wajib_diperiksa_tidak_melihat_tombol_selesai_tapi_pengurus_melihatnya(): void
    {
        $nivia = $this->warga('Nivia', 1, ['wajib_diperiksa' => true]);
        $p = $this->piket($this->mingguTerbit(), $nivia, JenisPenugasan::Harian, '2026-11-18');

        $this->actingAs($nivia)->get(route('warga.piket'))
            ->assertDontSee('Saya sudah selesai')
            ->assertSee('panggil pengurus untuk memeriksa');

        $this->masukSebagaiPengurus();
        $this->get(route('pengurus.pengawasan'))->assertSee('Periksa &amp; selesai', false)->assertDontSee('segera tersedia');

        Livewire::test(Pengawasan::class)->call('tandaiSelesai', $p->id)->assertDispatched('toast', jenis: 'sukses');
        $this->assertSame(StatusPenugasan::Selesai, $p->fresh()->status);
    }

    public function test_pengurus_membatalkan_selesai_dan_menulis_catatan_yang_terlihat_warga(): void
    {
        $ping = $this->warga('Ping', 1);
        $p = $this->piket($this->mingguTerbit(), $ping, JenisPenugasan::Harian, '2026-11-18', StatusPenugasan::Selesai);
        $this->masukSebagaiPengurus();

        Livewire::test(Pengawasan::class)
            ->assertSee('Batalkan')
            ->call('batalkanSelesai', $p->id)
            ->call('simpanCatatan', $p->id, 'Garasi belum dipel');

        $this->assertSame(StatusPenugasan::Terjadwal, $p->fresh()->status);

        $this->actingAs($ping)->get(route('warga.piket'))->assertSee('Catatan pengurus:')->assertSee('Garasi belum dipel');
    }

    public function test_warga_biasa_tidak_bisa_menjalankan_aksi_pengawasan(): void
    {
        $ping = $this->warga('Ping', 1);
        $p = $this->piket($this->mingguTerbit(), $ping, JenisPenugasan::Harian, '2026-11-18');
        $this->actingAs($ping);

        Livewire::test(Pengawasan::class)->assertForbidden();
        $this->assertSame(StatusPenugasan::Terjadwal, $p->fresh()->status);
    }
}
