<?php

namespace Tests\Feature;

use App\Enums\StatusPenugasan;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\User;
use App\Services\JadwalMinggu;
use App\Services\RingkasanWarga;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD bagian 15 R4: label status dan status baru Izin.
 */
class StatusPiketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 06:00', 'Asia/Makassar'));
    }

    public function test_label_terjadwal_menjadi_belum_dikerjakan(): void
    {
        $this->assertSame('Belum dikerjakan', StatusPenugasan::Terjadwal->label());
        $this->assertSame('terjadwal', StatusPenugasan::Terjadwal->value, 'Nilai di database tidak berubah');

        $warga = User::factory()->create(['nama' => 'Ping', 'geng_id' => $this->geng(1)->id]);
        $minggu = app(JadwalMinggu::class)->siapkan(now());
        app(JadwalMinggu::class)->aturHarian($minggu, CarbonImmutable::parse('2026-11-18', 'Asia/Makassar'), $warga);

        $this->actingAs($warga);
        $this->get(route('warga.jadwal'))->assertSee('Belum dikerjakan')->assertDontSee('>Terjadwal<', false);
        $this->get(route('warga.piket'))->assertSee('Belum dikerjakan');
    }

    public function test_status_izin_tersimpan_abu_abu_dan_tidak_dihitung_belum_selesai(): void
    {
        $warga = User::factory()->create(['geng_id' => $this->geng(1)->id]);
        $minggu = MingguPiket::create(['tanggal_senin' => '2026-11-16', 'geng_harian_id' => $this->geng(1)->id, 'status_harian' => 'terbit']);
        $p = Penugasan::create([
            'minggu_piket_id' => $minggu->id, 'user_id' => $warga->id, 'jenis' => 'harian',
            'tanggal' => '2026-11-19', 'tenggat' => '2026-11-19 09:00:00', 'status' => 'izin',
        ]);

        $this->assertSame(StatusPenugasan::Izin, $p->fresh()->status);
        $this->assertSame('Izin', StatusPenugasan::Izin->label());
        $this->assertSame('badge badge-izin', StatusPenugasan::Izin->badgeClass());
        $this->assertFalse(StatusPenugasan::Izin->belumSelesai());

        // Piket berstatus Izin bukan "tenggat terdekat" dan bukan bintang.
        $this->assertNull(app(RingkasanWarga::class)->tenggatTerdekat($warga));
        $this->assertSame(0, app(RingkasanWarga::class)->jumlahBintangBelumLunas($warga));

        $this->actingAs($warga)->get(route('warga.jadwal'))->assertSee('badge-izin', false)->assertSee('Izin');
    }

    public function test_status_draf_terbit_tidak_tampil_di_pengawasan(): void
    {
        $this->actingAs(User::factory()->pengurus()->create());

        $this->get(route('pengurus.pengawasan'))->assertOk()->assertDontSee('diterbitkan')->assertDontSee('Draf');
    }
}
