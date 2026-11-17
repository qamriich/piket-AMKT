<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusIzin;
use App\Enums\StatusPenugasan;
use App\Livewire\Pengurus\Pengajuan;
use App\Livewire\Warga\Piket;
use App\Models\Izin;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Notifications\IzinDiajukan;
use App\Notifications\IzinDiputuskan;
use App\Notifications\JadwalBerubah;
use App\Services\BintangOtomatis;
use App\Services\IzinPulang;
use App\Services\JadwalHarianPeriode;
use App\Services\JadwalMinggu;
use App\Services\PenugasanOtomatis;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * PRD bagian 15 R3: izin pulang dengan pengganti otomatis.
 */
class IzinPulangTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
        $this->waktu('2026-11-17 10:00'); // Selasa
        $this->app->instance(PenugasanOtomatis::class, app(PenugasanOtomatis::class)->denganPengacak(fn (array $ids) => $ids));
    }

    private function t(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal, config('app.timezone'));
    }

    private function izinDisetujui($user, string $mulai, string $selesai): array
    {
        return app(IzinPulang::class)->buatLangsung($user, $this->t($mulai), $this->t($selesai), 'Pulang kampung', $this->masukSebagaiPengurus());
    }

    public function test_warga_mengajukan_izin_dari_piket_saya(): void
    {
        $wilda = $this->warga('Wilda', 3, ['role' => 'tata_tertib']);
        $ping = $this->warga('Ping', 2);
        $this->actingAs($ping);

        $this->get(route('warga.piket'))->assertSee('Izin pulang');
        Livewire::test(Piket::class)
            ->set('izin_mulai', '2026-11-19')->set('izin_selesai', '2026-11-23')->set('izin_alasan', 'Pulang ke Samarinda')
            ->call('ajukanIzin')
            ->assertHasNoErrors()
            ->assertDispatched('toast', jenis: 'sukses');

        $izin = Izin::query()->sole();
        $this->assertSame(StatusIzin::Menunggu, $izin->status);
        Notification::assertSentTo($wilda, IzinDiajukan::class);
    }

    public function test_disetujui_piket_kamis_terbit_jadi_izin_dan_pengganti_otomatis(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1', 'WC 2']]);
        $nivia = $this->warga('Nivia', 2);
        $dilla = $this->warga('Dilla', 2);
        $gita = $this->warga('Gita', 3);
        $dewi = $this->warga('Dewi', 3);
        $this->warga('Harian', 1); // geng harian, bukan pool Kamis

        $wc1 = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');
        $this->piket($m, $dilla, JenisPenugasan::Kamis, tugas: 'WC 2');
        // Gita baru saja mengerjakan WC 1, Dewi belum pernah: Dewi yang dipilih.
        $lalu = $this->mingguTerbit('2026-11-09');
        $this->piket($lalu, $gita, JenisPenugasan::Kamis, tugas: 'WC 1', status: StatusPenugasan::Selesai);

        $hasil = $this->izinDisetujui($nivia, '2026-11-19', '2026-11-23')['hasil'];

        $this->assertSame(StatusPenugasan::Izin, $wc1->fresh()->status);
        $pengganti = $m->penugasan()->bukanIzin()->where('tugas_id', $this->tugas('WC 1')->id)->sole();
        $this->assertSame($dewi->id, $pengganti->user_id);
        $this->assertSame(['WC 1: Dewi'], $hasil['pengganti']);

        Notification::assertSentTo($dewi, JadwalBerubah::class, fn ($n) => str_contains($n->teks(), 'Nivia') && str_contains($n->teks(), 'WC 1'));
        Notification::assertSentTo($nivia, IzinDiputuskan::class, fn ($n) => str_contains($n->teks(), 'disetujui') && str_contains($n->teks(), 'WC 1'));
        Notification::assertNotSentTo($dilla, JadwalBerubah::class);

        // Piket Izin tidak menjadi bintang.
        $this->waktu('2026-11-21 10:00');
        app(BintangOtomatis::class)->proses();
        $this->assertSame(StatusPenugasan::Izin, $wc1->fresh()->status);
    }

    public function test_tidak_ada_calon_pengganti_tugas_belum_ada_petugas(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $wc1 = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');

        $hasil = $this->izinDisetujui($nivia, '2026-11-19', '2026-11-19')['hasil'];

        $this->assertSame(StatusPenugasan::Izin, $wc1->fresh()->status);
        $this->assertSame(['WC 1 (Kamis)'], $hasil['tanpa_petugas']);
        $this->assertSame(0, $m->penugasan()->bukanIzin()->count());
    }

    public function test_harian_menjadi_kosong_dan_pengurus_bisa_memilih_pengganti(): void
    {
        $m = $this->mingguTerbit();
        $ping = $this->warga('Ping', 1);
        $pengganti = $this->warga('Septi', 1);
        $harian = $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-20');

        $this->izinDisetujui($ping, '2026-11-20', '2026-11-20');
        $this->assertSame(StatusPenugasan::Izin, $harian->fresh()->status);

        app(JadwalMinggu::class)->aturHarian($m, $this->t('2026-11-20'), $pengganti);
        $baris = Penugasan::query()->whereDate('tanggal', '2026-11-20')->orderBy('id')->get();
        $this->assertSame([StatusPenugasan::Izin, StatusPenugasan::Terjadwal], $baris->pluck('status')->all());
        $this->assertSame($pengganti->id, $baris[1]->user_id);
    }

    public function test_pool_tanpa_warga_izin_dan_bintangnya_ditunda(): void
    {
        $gita = $this->warga('Gita', 2);
        $this->warga('A', 3);
        $asli = $this->piket($this->mingguTerbit('2026-11-09'), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'Pelataran');
        $m = $this->mingguTerbit(status: ['status_kamis' => 'draf'], aktif: ['kamis' => ['Pelataran', 'WC 1']]);
        $this->izinDisetujui($gita, '2026-11-18', '2026-11-20');

        $hasil = app(PenugasanOtomatis::class)->bagikan($m, SesiTugas::Kamis);

        $this->assertFalse($m->penugasan()->where('user_id', $gita->id)->exists());
        $this->assertSame($asli->id, $hasil['ditunda'][0]['bintang']->id);
        $this->assertSame('Pemiliknya sedang izin pulang', $hasil['ditunda'][0]['alasan']);
    }

    public function test_piket_draf_langsung_dipindah_tanpa_status_izin(): void
    {
        $m = $this->mingguTerbit(status: ['status_kamis' => 'draf'], aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $dewi = $this->warga('Dewi', 3);
        $p = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');

        $this->izinDisetujui($nivia, '2026-11-19', '2026-11-19');

        $this->assertSame([$dewi->id, StatusPenugasan::Terjadwal], [$p->fresh()->user_id, $p->fresh()->status]);
        Notification::assertNotSentTo($dewi, JadwalBerubah::class);
    }

    public function test_tolak_izin_piket_tetap_berjalan(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $p = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');
        $izin = app(IzinPulang::class)->ajukan($nivia, $this->t('2026-11-19'), $this->t('2026-11-20'), null);

        $this->masukSebagaiPengurus();
        Livewire::test(Pengajuan::class, ['tampil' => 'izin'])->assertSee('Nivia')
            ->call('tolakIzin', $izin->id, 'Ada rapat');

        $this->assertSame(StatusIzin::Ditolak, $izin->fresh()->status);
        $this->assertSame(StatusPenugasan::Terjadwal, $p->fresh()->status);
        Notification::assertSentTo($nivia, IzinDiputuskan::class, fn ($n) => str_contains($n->teks(), 'ditolak') && str_contains($n->teks(), 'Ada rapat'));
    }

    public function test_setujui_lewat_halaman_pengajuan_dan_tampil_di_pengawasan(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $this->warga('Dewi', 3);
        $p = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');
        $izin = app(IzinPulang::class)->ajukan($nivia, $this->t('2026-11-19'), $this->t('2026-11-20'), 'Keluarga sakit');

        $this->masukSebagaiPengurus();
        Livewire::test(Pengajuan::class, ['tampil' => 'izin'])->call('setujuiIzin', $izin->id, null)
            ->assertDispatched('toast', fn ($nama, $data) => str_contains($data['pesan'], 'Pengganti: WC 1: Dewi'));

        $this->assertSame(StatusPenugasan::Izin, $p->fresh()->status);
        $this->get(route('pengurus.pengawasan'))->assertSee('Sedang pulang minggu ini')->assertSee('Nivia')->assertSee('Keluarga sakit');
    }

    public function test_jadwal_harian_periode_mencatat_izin(): void
    {
        $ping = $this->warga('Ping', 1);
        $this->izinDisetujui($ping, '2026-11-25', '2026-11-25');

        $periode = app(JadwalHarianPeriode::class);
        $p = $periode->simpanPeriode(null, 'Ganjil', $this->t('2026-11-01'), $this->t('2027-01-31'));
        $periode->simpanHari($p, [$this->geng(1)->id => [3 => $ping->id]]); // Rabu
        // Minggu 23 Nov: geng harian dari rotasi = Geng 2, jadi geser acuan supaya Geng 1.
        Pengaturan::simpan('minggu_acuan', '2026-11-23');

        $minggu = app(JadwalMinggu::class)->siapkan($this->t('2026-11-25'));
        $this->assertSame(StatusPenugasan::Izin, $minggu->penugasan()->whereDate('tanggal', '2026-11-25')->sole()->status);
    }

    public function test_rentang_tidak_sah_ditolak(): void
    {
        $ping = $this->warga('Ping', 1);
        $this->actingAs($ping);

        Livewire::test(Piket::class)->set('izin_mulai', '2026-11-20')->set('izin_selesai', '2026-11-19')->call('ajukanIzin')
            ->assertDispatched('toast', jenis: 'gagal');
        Livewire::test(Piket::class)->set('izin_mulai', '2026-11-10')->set('izin_selesai', '2026-11-12')->call('ajukanIzin')
            ->assertDispatched('toast', jenis: 'gagal');
        $this->assertSame(0, Izin::query()->count());
    }
}
