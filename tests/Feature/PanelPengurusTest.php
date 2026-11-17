<?php

namespace Tests\Feature;

use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Livewire\Pengurus\AturJadwal;
use App\Livewire\Pengurus\Pengawasan;
use App\Livewire\Pengurus\Rekap;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use App\Services\JadwalMinggu;
use Carbon\CarbonImmutable;
use Database\Seeders\TugasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel pengurus dan alur Tahap 2 (atur jadwal) tanpa reload.
 */
class PanelPengurusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->seed(TugasSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00', 'Asia/Makassar')); // Rabu, Geng 1 harian
    }

    private function warga(int $geng, int $jumlah): void
    {
        User::factory()->count($jumlah)->create(['geng_id' => $this->geng($geng)->id]);
    }

    public function test_semua_halaman_pengurus_tampil(): void
    {
        $this->masukSebagaiPengurus();
        $this->warga(1, 3);

        foreach (['pengurus.pengawasan', 'pengurus.jadwal', 'pengurus.pengajuan', 'pengurus.warga.index', 'pengurus.warga.create',
            'pengurus.tugas.index', 'pengurus.rekap', 'pengurus.pengaturan'] as $rute) {
            $this->get(route($rute))->assertOk();
        }

        $this->get(route('pengurus.pengawasan'))
            ->assertSee('Selamat pagi,', false) // jam 10.00 WITA
            ->assertSee('Piket hari ini')
            ->assertSee('Pengajuan ganti bintang')
            ->assertSee('Atur piket Minggu, 22 Nov');
    }

    public function test_warga_biasa_ditolak_dan_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('pengurus.jadwal'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('pengurus.pengawasan'))->assertForbidden();
        $this->get(route('pengurus.jadwal'))->assertForbidden();
    }

    public function test_panel_punya_semua_menu_dan_tampilan_warga(): void
    {
        $this->masukSebagaiPengurus();

        $this->get(route('pengurus.pengawasan'))->assertOk()->assertSeeInOrder([
            'Pengawasan', 'Atur jadwal', 'Pengajuan', 'Warga &amp; geng', 'Rekap bulanan', 'Pengaturan', 'TAMPILAN WARGA',
        ], false);
    }

    public function test_tampilan_warga_milik_pengurus_punya_tombol_panel_pengurus(): void
    {
        $this->masukSebagaiPengurus();
        $this->get(route('dashboard'))->assertOk()->assertSee('Panel pengurus')->assertSee(route('pengurus.pengawasan'), false);

        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Panel pengurus');
    }

    public function test_alur_harian_buat_draf_ubah_lalu_terbitkan(): void
    {
        $this->masukSebagaiPengurus();
        $this->warga(1, 3);
        $lain = User::factory()->create(['geng_id' => $this->geng(2)->id]);

        $c = Livewire::test(AturJadwal::class)->call('buatDrafHarian')->assertDispatched('toast');
        $minggu = MingguPiket::query()->firstOrFail();
        $this->assertSame(3, $minggu->penugasan()->where('jenis', 'harian')->count());

        // Mengosongkan hari dan menolak warga dari geng lain.
        $hari = $minggu->penugasan()->where('jenis', 'harian')->first()->tanggal->toDateString();
        $c->call('aturHarian', $hari, '');
        $this->assertSame(2, $minggu->penugasan()->where('jenis', 'harian')->count());
        $c->call('aturHarian', $hari, $lain->id)->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');

        $c->call('terbitkan', 'harian');
        $this->assertTrue($minggu->fresh()->sudahTerbit('harian'));
        $this->get(route('pengurus.jadwal'))->assertSee('Sudah diterbitkan');
    }

    public function test_alur_kamis_toggle_bagikan_tukar_terbitkan(): void
    {
        $this->masukSebagaiPengurus();
        $this->warga(2, 3);
        $this->warga(3, 3);

        $c = Livewire::test(AturJadwal::class)->call('pilihTab', 'kamis')->assertSet('tab', 'kamis');
        $minggu = MingguPiket::query()->firstOrFail();

        // Semua tugas aktif secara default (13), matikan semua lalu nyalakan 4.
        $c->call('semuaTugas', 'kamis', false);
        $this->assertCount(0, app(JadwalMinggu::class)->tugasAktif($minggu, SesiTugas::Kamis));
        foreach (Tugas::query()->whereIn('nama', ['WC 1', 'WC 2', 'WC 3', 'WC 4'])->get() as $t) {
            $c->call('ubahTugas', 'kamis', $t->id, true);
        }

        $c->call('bagikan', 'kamis')->assertDispatched('toast')->assertSee('Bebas minggu ini (2)');
        $baris = $minggu->penugasan()->where('jenis', 'kamis')->get();
        $this->assertCount(4, $baris);
        $this->assertSame(4, $baris->pluck('user_id')->unique()->count());

        [$a, $b] = $baris->take(2)->values()->all();
        $c->call('pilihTukar', $a->id)->assertSet('tukarDari', $a->id)->call('pilihTukar', $b->id)->assertSet('tukarDari', null);
        $this->assertSame($b->user_id, $a->fresh()->user_id);
        $this->assertSame($a->user_id, $b->fresh()->user_id);

        $c->call('terbitkan', 'kamis');
        $this->assertTrue($minggu->fresh()->sudahTerbit('kamis'));

        // Setelah terbit daftar tugas terkunci.
        $c->call('ubahTugas', 'kamis', Tugas::query()->where('nama', 'Garasi')->value('id'), true)
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
    }

    public function test_tidak_bisa_terbitkan_kalau_belum_ada_pembagian(): void
    {
        $this->masukSebagaiPengurus();

        Livewire::test(AturJadwal::class)->call('terbitkan', 'kamis')
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
        $this->assertFalse(MingguPiket::query()->firstOrFail()->sudahTerbit('kamis'));
    }

    public function test_kartu_cepat_minggu_membagikan_lalu_ke_halaman_atur_jadwal(): void
    {
        $this->masukSebagaiPengurus();
        $this->warga(1, 5);

        Livewire::test(Pengawasan::class)
            ->call('ubahModeMinggu', 'kerja_bakti')
            ->call('bagikanMinggu')
            ->assertRedirect(route('pengurus.jadwal', ['minggu' => '2026-11-16', 'tab' => 'minggu']));

        // 5 warga + 1 pengurus aktif ikut kerja bakti.
        $this->assertSame(6, Penugasan::query()->where('jenis', 'kerja_bakti')->count());
    }

    public function test_pengawasan_menampilkan_piket_terbit_hari_ini_dengan_wajib_diperiksa_di_atas(): void
    {
        $this->masukSebagaiPengurus();
        $biasa = User::factory()->create(['nama' => 'Ayu Biasa', 'geng_id' => $this->geng(1)->id]);
        $diperiksa = User::factory()->create(['nama' => 'Wulan Periksa', 'geng_id' => $this->geng(1)->id, 'wajib_diperiksa' => true]);
        $minggu = app(JadwalMinggu::class)->siapkan(now());
        app(JadwalMinggu::class)->aturHarian($minggu, CarbonImmutable::parse('2026-11-18', 'Asia/Makassar'), $diperiksa);
        app(JadwalMinggu::class)->aturHarian($minggu, CarbonImmutable::parse('2026-11-19', 'Asia/Makassar'), $biasa);

        // Belum terbit: tidak muncul.
        Livewire::test(Pengawasan::class)->assertDontSee('Wulan Periksa');

        app(JadwalMinggu::class)->terbitkan($minggu, 'harian');
        Livewire::test(Pengawasan::class)
            ->assertSee('Wulan Periksa')->assertSee('Wajib diperiksa')->assertDontSee('Ayu Biasa')
            ->call('setRentang', 'minggu')
            ->assertSeeInOrder(['Wulan Periksa', 'Ayu Biasa'])
            ->call('setFilter', 'selesai')
            ->assertSee('Tidak ada piket di saringan ini.')
            ->assertDontSee('Ayu Biasa');
    }

    public function test_rekap_bulanan_menghitung_dari_data_penugasan(): void
    {
        $this->masukSebagaiPengurus();
        $u = User::factory()->create(['nama' => 'Gita Permata', 'geng_id' => $this->geng(1)->id]);
        $minggu = app(JadwalMinggu::class)->siapkan(now());
        $minggu->update(['status_harian' => 'terbit']);
        foreach ([['2026-11-16', StatusPenugasan::Selesai], ['2026-11-17', StatusPenugasan::Bintang]] as [$tgl, $status]) {
            Penugasan::create([
                'minggu_piket_id' => $minggu->id, 'user_id' => $u->id, 'jenis' => 'harian',
                'tanggal' => $tgl, 'tenggat' => "{$tgl} 09:00:00", 'status' => $status,
            ]);
        }

        Livewire::test(Rekap::class)
            ->assertSet('bulan', '2026-11')
            ->assertViewHas('warga', function ($warga) use ($u) {
                $r = $warga->firstWhere('user.id', $u->id);

                return $r['terjadwal'] === 2 && $r['selesai'] === 1 && $r['bintang_baru'] === 1 && $r['utang'] === 1;
            });
    }
}
