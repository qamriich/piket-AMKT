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
use App\Services\JadwalHarianPeriode;
use App\Services\JadwalMinggu;
use App\Services\LayananIzin;
use App\Services\PenugasanOtomatis;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * PRD bagian 15 R3 dan R3a: izin dengan tanggal + jam, pengganti otomatis hanya kalau izin
 * menutupi seluruh waktu piket.
 */
class IzinTest extends TestCase
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

    private function t(string $waktu): CarbonImmutable
    {
        return CarbonImmutable::parse($waktu, config('app.timezone'));
    }

    private function izinDisetujui($user, string $mulai, string $selesai): array
    {
        return app(LayananIzin::class)->buatLangsung($user, $this->t($mulai), $this->t($selesai), 'Kegiatan kampus', $this->masukSebagaiPengurus());
    }

    public function test_izin_menutupi_seluruh_waktu_kamis_piket_diganti_otomatis(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1', 'WC 2']]);
        $nivia = $this->warga('Nivia', 2);
        $dilla = $this->warga('Dilla', 2);
        $gita = $this->warga('Gita', 3);
        $dewi = $this->warga('Dewi', 3);
        $this->warga('Harian', 1); // geng harian, bukan pool Kamis

        $wc1 = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');
        $this->piket($m, $dilla, JenisPenugasan::Kamis, tugas: 'WC 2');
        // Gita baru saja mengerjakan WC 1, Dewi belum pernah: Dewi yang dipilih (B3 langkah 2).
        $this->piket($this->mingguTerbit('2026-11-09'), $gita, JenisPenugasan::Kamis, tugas: 'WC 1', status: StatusPenugasan::Selesai);

        // Jendela Kamis: Kamis 00.00 sampai Jumat 18.00 (R3a). Izin Rabu 20.00 – Jumat 20.00 menutupi seluruhnya.
        $hasil = $this->izinDisetujui($nivia, '2026-11-18 20:00', '2026-11-20 20:00')['hasil'];

        $this->assertSame(StatusPenugasan::Izin, $wc1->fresh()->status);
        $pengganti = $m->penugasan()->bukanIzin()->where('tugas_id', $this->tugas('WC 1')->id)->sole();
        $this->assertSame($dewi->id, $pengganti->user_id);
        $this->assertSame(['WC 1: Dewi'], $hasil['pengganti']);

        Notification::assertSentTo($dewi, JadwalBerubah::class, fn ($n) => str_contains($n->teks(), 'Nivia sedang izin') && str_contains($n->teks(), 'WC 1'));
        Notification::assertSentTo($nivia, IzinDiputuskan::class, fn ($n) => str_contains($n->teks(), 'disetujui') && str_contains($n->teks(), 'WC 1'));
        Notification::assertNotSentTo($dilla, JadwalBerubah::class);

        // Piket Izin tidak menjadi bintang.
        $this->waktu('2026-11-21 10:00');
        app(BintangOtomatis::class)->proses();
        $this->assertSame(StatusPenugasan::Izin, $wc1->fresh()->status);
    }

    public function test_izin_sebagian_piket_tetap_miliknya(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $this->warga('Dewi', 3);
        $wc1 = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');
        $harian = $this->piket($m, $nivia, JenisPenugasan::Harian, '2026-11-21');

        // Pergi Kamis 10.00–14.00: masih ada waktu sampai Jumat 18.00. Izin Sabtu 05.00–20.00: harian 04.00–09.00 tidak tertutup penuh.
        $hasil = $this->izinDisetujui($nivia, '2026-11-19 10:00', '2026-11-19 14:00')['hasil'];
        $this->izinDisetujui($nivia, '2026-11-21 05:00', '2026-11-21 20:00');

        $this->assertSame([], $hasil['dilepas']);
        $this->assertSame(StatusPenugasan::Terjadwal, $wc1->fresh()->status);
        $this->assertSame(StatusPenugasan::Terjadwal, $harian->fresh()->status);
        $this->assertSame(1, $m->penugasan()->where('jenis', 'kamis')->count());
    }

    public function test_batas_jendela_tiap_jenis(): void
    {
        $m = $this->mingguTerbit(aktif: ['minggu' => ['WC 1']]);
        $ping = $this->warga('Ping', 1);
        $harian = $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-20');
        $minggu = $this->piket($m, $ping, JenisPenugasan::Minggu, tugas: 'WC 1');

        // Harian Jumat 04.00–09.00 tertutup penuh; Minggu sampai Senin 17.59 tidak menutupi batas Senin 18.00.
        $this->izinDisetujui($ping, '2026-11-20 04:00', '2026-11-20 09:00');
        $this->izinDisetujui($ping, '2026-11-22 00:00', '2026-11-23 17:59');

        $this->assertSame(StatusPenugasan::Izin, $harian->fresh()->status);
        $this->assertSame(StatusPenugasan::Terjadwal, $minggu->fresh()->status);
    }

    public function test_warga_mengajukan_izin_dengan_jam_dan_tombol_cepat(): void
    {
        $wilda = $this->warga('Wilda', 3, ['role' => 'tata_tertib']);
        $ping = $this->warga('Ping', 2);
        $this->actingAs($ping);

        $this->get(route('warga.piket'))->assertSee('Izin')->assertDontSee('Izin pulang')
            ->assertSee('Hari ini sampai besok')->assertSee('Sehari penuh')->assertSee('datetime-local', false);

        Livewire::test(Piket::class)
            ->call('izinHariIniBesok')
            ->assertSet('izin_mulai', '2026-11-17T00:00')
            ->assertSet('izin_selesai', '2026-11-18T23:59')
            ->set('izin_mulai', '2026-11-19T08:00')
            ->call('izinSehariPenuh')
            ->assertSet('izin_mulai', '2026-11-19T00:00')
            ->assertSet('izin_selesai', '2026-11-19T23:59')
            ->set('izin_selesai', '2026-11-20T20:00')->set('izin_alasan', 'KKN')
            ->call('ajukanIzin')
            ->assertHasNoErrors()
            ->assertDispatched('toast', jenis: 'sukses');

        $izin = Izin::query()->sole();
        $this->assertSame(StatusIzin::Menunggu, $izin->status);
        $this->assertSame('2026-11-19 00:00', $izin->mulai->format('Y-m-d H:i'));
        $this->assertSame('2026-11-20 20:00', $izin->selesai->format('Y-m-d H:i'));
        $this->assertSame('Kamis, 19 Nov 00.00 – Jumat, 20 Nov 20.00', $izin->rentangLabel());
        Notification::assertSentTo($wilda, IzinDiajukan::class, fn ($n) => str_contains($n->subjek, 'Izin dari') && ! str_contains($n->teks(), 'pulang'));
    }

    public function test_tidak_ada_calon_pengganti_tugas_belum_ada_petugas(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $wc1 = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');

        $hasil = $this->izinDisetujui($nivia, '2026-11-19 00:00', '2026-11-20 18:00')['hasil'];

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

        $this->izinDisetujui($ping, '2026-11-20 00:00', '2026-11-20 23:59');
        $this->assertSame(StatusPenugasan::Izin, $harian->fresh()->status);

        app(JadwalMinggu::class)->aturHarian($m, $this->t('2026-11-20'), $pengganti);
        $baris = Penugasan::query()->whereDate('tanggal', '2026-11-20')->orderBy('id')->get();
        $this->assertSame([StatusPenugasan::Izin, StatusPenugasan::Terjadwal], $baris->pluck('status')->all());
        $this->assertSame($pengganti->id, $baris[1]->user_id);
    }

    public function test_pool_hanya_tanpa_warga_yang_izinnya_menutupi_seluruh_waktu(): void
    {
        $gita = $this->warga('Gita', 2);
        $sebentar = $this->warga('Sebentar', 3);
        $asli = $this->piket($this->mingguTerbit('2026-11-09'), $gita, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'Pelataran');
        $m = $this->mingguTerbit(status: ['status_kamis' => 'draf'], aktif: ['kamis' => ['Pelataran', 'WC 1']]);
        $this->izinDisetujui($gita, '2026-11-18 12:00', '2026-11-21 12:00');
        $this->izinDisetujui($sebentar, '2026-11-19 10:00', '2026-11-19 14:00');

        $pool = app(JadwalMinggu::class)->pool($m, SesiTugas::Kamis)->pluck('id')->all();
        $this->assertSame([$sebentar->id], $pool, 'Izin sebagian tetap masuk pool');

        $hasil = app(PenugasanOtomatis::class)->bagikan($m, SesiTugas::Kamis);
        $this->assertFalse($m->penugasan()->where('user_id', $gita->id)->exists());
        $this->assertSame($asli->id, $hasil['ditunda'][0]['bintang']->id);
        $this->assertSame('Pemiliknya sedang izin', $hasil['ditunda'][0]['alasan']);
    }

    public function test_piket_draf_langsung_dipindah_tanpa_status_izin(): void
    {
        $m = $this->mingguTerbit(status: ['status_kamis' => 'draf'], aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $dewi = $this->warga('Dewi', 3);
        $p = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');

        $this->izinDisetujui($nivia, '2026-11-19 00:00', '2026-11-20 18:00');

        $this->assertSame([$dewi->id, StatusPenugasan::Terjadwal], [$p->fresh()->user_id, $p->fresh()->status]);
        Notification::assertNotSentTo($dewi, JadwalBerubah::class);
    }

    public function test_tolak_izin_piket_tetap_berjalan(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $p = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');
        $izin = app(LayananIzin::class)->ajukan($nivia, $this->t('2026-11-19 00:00'), $this->t('2026-11-20 23:00'), null);

        $this->masukSebagaiPengurus();
        Livewire::test(Pengajuan::class, ['tampil' => 'izin'])->assertSee('Nivia')
            ->call('tolakIzin', $izin->id, 'Ada rapat');

        $this->assertSame(StatusIzin::Ditolak, $izin->fresh()->status);
        $this->assertSame(StatusPenugasan::Terjadwal, $p->fresh()->status);
        Notification::assertSentTo($nivia, IzinDiputuskan::class, fn ($n) => str_contains($n->teks(), 'ditolak') && str_contains($n->teks(), 'Ada rapat'));
    }

    public function test_pengurus_membuat_izin_dan_tampil_di_pengawasan(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $this->warga('Dewi', 3);
        $p = $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');
        $this->masukSebagaiPengurus();

        Livewire::test(Pengajuan::class, ['tampil' => 'izin'])
            ->assertSee('Buat izin')->assertDontSee('Izin pulang')
            ->set('izin_user', (string) $nivia->id)
            ->set('izin_mulai', '2026-11-19T00:00')->set('izin_selesai', '2026-11-20T20:00')->set('izin_alasan', 'Keluarga sakit')
            ->call('buatIzin')
            ->assertHasNoErrors()
            ->assertDispatched('toast', fn ($nama, $data) => str_contains($data['pesan'], 'Pengganti: WC 1: Dewi'));

        $this->assertSame(StatusPenugasan::Izin, $p->fresh()->status);
        $this->get(route('pengurus.pengawasan'))->assertSee('Sedang izin minggu ini')->assertSee('Nivia')->assertSee('Keluarga sakit');
    }

    public function test_jadwal_harian_periode_mencatat_izin_yang_menutupi_jam_piket(): void
    {
        $ping = $this->warga('Ping', 1);
        $this->izinDisetujui($ping, '2026-11-25 03:00', '2026-11-25 10:00');

        $periode = app(JadwalHarianPeriode::class);
        $p = $periode->simpanPeriode(null, 'Ganjil', $this->t('2026-11-01'), $this->t('2027-01-31'));
        $periode->simpanHari($p, [$this->geng(1)->id => [3 => $ping->id]]); // Rabu
        Pengaturan::simpan('minggu_acuan', '2026-11-23'); // supaya minggu 23 Nov = Geng 1

        $minggu = app(JadwalMinggu::class)->siapkan($this->t('2026-11-25'));
        $this->assertSame(StatusPenugasan::Izin, $minggu->penugasan()->whereDate('tanggal', '2026-11-25')->sole()->status);
    }

    public function test_rentang_tidak_sah_ditolak(): void
    {
        $ping = $this->warga('Ping', 1);
        $this->actingAs($ping);

        Livewire::test(Piket::class)->set('izin_mulai', '2026-11-20T10:00')->set('izin_selesai', '2026-11-20T09:00')->call('ajukanIzin')
            ->assertDispatched('toast', jenis: 'gagal');
        Livewire::test(Piket::class)->set('izin_mulai', '2026-11-10T08:00')->set('izin_selesai', '2026-11-12T08:00')->call('ajukanIzin')
            ->assertDispatched('toast', jenis: 'gagal');
        Livewire::test(Piket::class)->set('izin_mulai', '2026-11-20')->set('izin_selesai', '2026-11-21')->call('ajukanIzin')
            ->assertHasErrors(['izin_mulai', 'izin_selesai']);
        $this->assertSame(0, Izin::query()->count());
    }
}
