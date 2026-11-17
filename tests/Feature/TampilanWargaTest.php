<?php

namespace Tests\Feature;

use App\Enums\SesiTugas;
use App\Livewire\JadwalKuliahEditor;
use App\Livewire\Pengurus\AturJadwal;
use App\Livewire\Warga\Profil;
use App\Models\JadwalKuliah;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use App\Notifications\TautanPassword;
use App\Services\DrafHarian;
use App\Services\JadwalMinggu;
use App\Services\PenugasanOtomatis;
use App\Services\RingkasanWarga;
use Carbon\CarbonImmutable;
use Database\Seeders\TugasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Web warga: 5 tab + profil, data asli dari database, hanya jadwal yang sudah terbit.
 */
class TampilanWargaTest extends TestCase
{
    use RefreshDatabase;

    private const RUTE = ['dashboard', 'warga.piket', 'warga.utang', 'warga.jadwal', 'warga.kuliah', 'warga.profil'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->seed(TugasSeeder::class);
        // Kamis, 19 Nov 2026 jam 10.00 WITA. Minggu acuan 16 Nov = Geng 1 harian.
        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00', 'Asia/Makassar'));
    }

    /** Membuat pembagian Kamis untuk minggu ini. */
    private function kamisMingguIni(bool $terbit): array
    {
        $gita = User::factory()->create(['nama' => 'Gita Permata', 'geng_id' => $this->geng(2)->id]);
        $nivia = User::factory()->create(['nama' => 'Nivia Salsabila', 'geng_id' => $this->geng(3)->id]);

        $layanan = app(JadwalMinggu::class);
        $minggu = $layanan->siapkan(now());
        foreach (Tugas::query()->where('jenis', 'kamis_minggu')->get() as $t) {
            $layanan->ubahTugasAktif($minggu, SesiTugas::Kamis, $t, in_array($t->nama, ['WC 1', 'WC 2'], true));
        }
        app(PenugasanOtomatis::class)->bagikan($minggu, SesiTugas::Kamis);
        if ($terbit) {
            $layanan->terbitkan($minggu, 'kamis');
        }

        return [$gita, $nivia, $minggu];
    }

    public function test_semua_halaman_warga_tampil_walau_belum_ada_jadwal(): void
    {
        $this->actingAs(User::factory()->create(['nama' => 'Ping Lestari', 'geng_id' => $this->geng(1)->id]));

        foreach (self::RUTE as $rute) {
            $this->get(route($rute))->assertOk();
        }

        $this->get(route('dashboard'))
            ->assertSee('Halo,', false)->assertSee('Ping')
            ->assertSee('Yang perlu kamu tahu hari ini')
            ->assertSee('Kamu bebas piket')
            ->assertSee('Geng 1 piket harian');
        $this->get(route('warga.piket'))->assertSee('Minggu ini kamu bebas piket');
        $this->get(route('warga.utang'))->assertSee('Kamu tidak punya utang piket');
        $this->get(route('warga.jadwal'))->assertSee('Belum diterbitkan');
    }

    public function test_draf_tidak_terlihat_warga_sampai_diterbitkan(): void
    {
        [$gita] = $this->kamisMingguIni(terbit: false);
        $this->actingAs($gita);

        $this->get(route('warga.piket'))->assertSee('Minggu ini kamu bebas piket')->assertDontSee('WC 1');
        $this->get(route('warga.jadwal'))->assertSee('Belum diterbitkan');
    }

    public function test_setelah_terbit_piket_muncul_di_beranda_piket_dan_jadwal(): void
    {
        [$gita, $nivia] = $this->kamisMingguIni(terbit: true);
        $tugasGita = Penugasan::query()->where('user_id', $gita->id)->with('tugas')->firstOrFail()->tugas->nama;
        $this->actingAs($gita);

        $this->get(route('dashboard'))
            ->assertSee('Kamu dapat piket Kamis minggu ini')
            ->assertSee('Tenggat terdekat')
            ->assertSee('PIKET KAMIS, 19 NOV')
            ->assertSee($tugasGita);

        $this->get(route('warga.piket'))->assertSee($tugasGita)->assertSee('Saya sudah selesai')->assertSee('Batas: Jumat, 20 Nov');
        $this->get(route('warga.jadwal'))->assertSee('Gita Permata (kamu)')->assertSee('Nivia Salsabila');
    }

    public function test_warga_wajib_diperiksa_tidak_melihat_tombol_selesai(): void
    {
        [$gita] = $this->kamisMingguIni(terbit: true);
        $gita->update(['wajib_diperiksa' => true]);
        $this->actingAs($gita);

        $this->get(route('warga.piket'))
            ->assertDontSee('Saya sudah selesai')
            ->assertSee('Pengurus yang akan menandai piketmu selesai');
    }

    public function test_bintang_tampil_di_utang_dan_angka_beranda(): void
    {
        [$gita, , $minggu] = $this->kamisMingguIni(terbit: true);
        $lalu = app(JadwalMinggu::class)->siapkan(now()->subWeek());
        $lalu->update(['status_harian' => 'terbit']);
        Penugasan::create([
            'minggu_piket_id' => $lalu->id, 'user_id' => $gita->id, 'jenis' => 'harian',
            'tanggal' => '2026-11-10', 'tenggat' => '2026-11-10 09:00:00', 'status' => 'bintang',
        ]);
        $this->actingAs($gita);

        $this->get(route('dashboard'))->assertSee('Bintang belum lunas')->assertSee('1 bintang belum lunas');
        $this->get(route('warga.utang'))->assertSee('Piket harian · Selasa, 10 Nov')->assertSee('Belum dikerjakan sampai jam 09.00.');
    }

    public function test_tanggal_kosong_hanya_hari_tanpa_petugas_di_minggu_yang_terbit(): void
    {
        $a = User::factory()->create(['geng_id' => $this->geng(1)->id]);
        $minggu = app(JadwalMinggu::class)->siapkan(now());
        app(JadwalMinggu::class)->aturHarian($minggu, CarbonImmutable::parse('2026-11-21', 'Asia/Makassar'), $a);
        app(JadwalMinggu::class)->terbitkan($minggu, 'harian');

        $kosong = app(RingkasanWarga::class)->tanggalKosong()->map->toDateString()->all();

        // Hari ini Kamis 19 Nov: yang tersisa Jumat 20 dan Minggu 22 (Sabtu 21 sudah terisi).
        $this->assertSame(['2026-11-20', '2026-11-22'], $kosong);
    }

    public function test_jadwal_kuliah_bisa_diisi_warga_sendiri(): void
    {
        $saya = User::factory()->create(['geng_id' => $this->geng(1)->id]);
        $this->actingAs($saya);

        Livewire::test(JadwalKuliahEditor::class)
            ->call('mulaiUbah')
            ->call('tambah', 2)
            ->set('sesi.0.mulai', '08:00')
            ->set('sesi.0.selesai', '10:30')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('ubah', false)
            ->assertDispatched('toast')
            ->assertSee('08.00–10.30');

        $this->assertDatabaseHas('jadwal_kuliah', ['user_id' => $saya->id, 'hari' => 2]);
        $this->assertSame([1, 3, 4, 5, 6, 7], app(DrafHarian::class)->hariBisa(collect([$saya]))[$saya->id]);
    }

    public function test_jam_selesai_harus_setelah_jam_mulai(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(JadwalKuliahEditor::class)
            ->call('tambah', 1)->set('sesi.0.mulai', '10:00')->set('sesi.0.selesai', '09:00')
            ->call('simpan')->assertHasErrors('sesi.0.selesai');
        $this->assertSame(0, JadwalKuliah::query()->count());
    }

    public function test_warga_tidak_bisa_mengubah_jadwal_kuliah_orang_lain(): void
    {
        $orang = User::factory()->create();
        $this->actingAs(User::factory()->create());

        Livewire::test(JadwalKuliahEditor::class, ['userId' => $orang->id])->assertForbidden();
    }

    public function test_ganti_password_dari_profil_mengirim_link_ke_email_sendiri_dan_dibatasi(): void
    {
        Notification::fake();
        $saya = User::factory()->create(['email' => 'ping@contoh.test']);
        $this->actingAs($saya);

        Livewire::test(Profil::class)->call('kirimGantiPassword')->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'sukses');
        Notification::assertSentTo($saya, TautanPassword::class, fn (TautanPassword $n) => $n->jenis === TautanPassword::RESET);

        // Percobaan kedua dalam 10 menit ditolak.
        Livewire::test(Profil::class)->call('kirimGantiPassword')->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
        Notification::assertSentToTimes($saya, TautanPassword::class, 1);
    }

    public function test_warga_biasa_tidak_bisa_menjalankan_komponen_pengurus(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AturJadwal::class)->assertForbidden();
    }
}
