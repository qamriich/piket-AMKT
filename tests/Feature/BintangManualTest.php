<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Livewire\Pengurus\Bintang;
use App\Livewire\Pengurus\Rekap;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\PeriodeHarian;
use App\Models\Tugas;
use App\Services\BintangManual;
use App\Services\JadwalMinggu;
use App\Services\PenugasanOtomatis;
use Carbon\CarbonImmutable;
use Database\Seeders\TugasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * Bintang manual: utang bintang lama yang dicatat pengurus, lalu dijadwalkan bayar seperti bintang biasa.
 */
class BintangManualTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->seed(TugasSeeder::class);
        Notification::fake();
        $this->waktu('2026-11-23 10:00'); // Senin
    }

    private function layanan(): BintangManual
    {
        return app(BintangManual::class);
    }

    private function tgl(string $t): CarbonImmutable
    {
        return CarbonImmutable::parse($t, config('app.timezone'));
    }

    public function test_bintang_kamis_lama_tercatat_di_minggunya_dan_terhitung_di_rekap(): void
    {
        $gita = $this->warga('Gita', 1);

        $b = $this->layanan()->tambah($gita, JenisPenugasan::Kamis, $this->tgl('2026-09-17'), $this->tugas('Rak piring'), 'catatan September');

        $this->assertSame(StatusPenugasan::Bintang, $b->status);
        $this->assertTrue($b->manual);
        $this->assertSame('2026-09-14', $b->mingguPiket->tanggal_senin->toDateString());
        $this->assertSame('2026-09-18 18:00', $b->tenggat->format('Y-m-d H:i'));
        $this->assertNotNull($b->diberitahu_pada);
        $this->assertNotNull($b->pengingat_dikirim_pada);
        $this->assertTrue($b->mingguPiket->sudahTerbit('kamis'));
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'bintang.manual_ditambah')->exists());
        Notification::assertNothingSent();

        $this->masukSebagaiPengurus();
        Livewire::test(Rekap::class, ['bulan' => '2026-09'])->assertViewHas('warga', function ($warga) use ($gita) {
            $r = $warga->firstWhere('user.id', $gita->id);

            return $r['bintang_baru'] === 1 && $r['utang'] === 1;
        });
    }

    public function test_bintang_manual_dijadwalkan_bayar_di_kamis_berikutnya_dengan_tugas_yang_sama(): void
    {
        // Minggu 23 Nov: geng 2 piket harian, jadi pool Kamis = geng 1 dan geng 3.
        $gita = $this->warga('Gita', 3);
        $this->warga('Nivia', 1);
        $this->warga('Ping', 3);
        $b = $this->layanan()->tambah($gita, JenisPenugasan::Kamis, $this->tgl('2026-09-24'), $this->tugas('Rak piring'));

        $minggu = app(JadwalMinggu::class)->siapkan(now());
        $this->assertSame(2, $minggu->gengHarian->urutan);
        $this->assertTrue($minggu->tugasAktif()->where('sesi', 'kamis')->where('tugas_id', $this->tugas('Rak piring')->id)->exists(),
            'Tugas baru yang aktif default ikut aktif di minggu ini.');

        app(PenugasanOtomatis::class)->bagikan($minggu, SesiTugas::Kamis);

        $bayar = Penugasan::query()->where('membayar_penugasan_id', $b->id)->first();
        $this->assertNotNull($bayar);
        $this->assertSame(StatusPenugasan::BayarBintang, $bayar->status);
        $this->assertSame($gita->id, $bayar->user_id);
        $this->assertSame($this->tugas('Rak piring')->id, $bayar->tugas_id);
    }

    public function test_minggu_lampau_tidak_pernah_menerapkan_jadwal_periode(): void
    {
        // Periode yang mencakup September, dengan petugas di setiap hari untuk setiap geng.
        $periode = PeriodeHarian::create(['nama' => 'Semester ganjil', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-12-31']);
        foreach ([1, 2, 3] as $g) {
            foreach (range(1, 7) as $hari) {
                $orang = $this->warga("Anggota {$g}-{$hari}", $g);
                $periode->hari()->create(['geng_id' => $this->geng($g)->id, 'hari' => $hari, 'user_id' => $orang->id]);
            }
        }

        $this->layanan()->tambah($this->warga('Septi', 2), JenisPenugasan::Kamis, $this->tgl('2026-09-17'), $this->tugas('WC 3'));
        $minggu = MingguPiket::query()->whereDate('tanggal_senin', '2026-09-14')->sole();
        $this->assertSame(1, $minggu->penugasan()->count(), 'Hanya bintang manual, tanpa piket harian dari periode.');
        $this->assertTrue($minggu->sudahTerbit('harian') && $minggu->sudahTerbit('kamis') && $minggu->sudahTerbit('minggu'));

        // Walaupun minggu itu nanti dibuka lewat siapkan() (misalnya dari halaman Atur jadwal), tetap tidak ada piket harian lampau.
        app(JadwalMinggu::class)->siapkan($this->tgl('2026-09-16'));
        $this->assertSame(1, $minggu->penugasan()->count());

        // Pembanding: minggu yang belum punya catatan memang terisi dari periode.
        $lain = app(JadwalMinggu::class)->siapkan($this->tgl('2026-09-23'));
        $this->assertGreaterThan(0, $lain->penugasan()->count());
    }

    public function test_aturan_tanggal_dan_tugas(): void
    {
        $u = $this->warga('Ain', 3);
        $kasus = [
            [JenisPenugasan::Kamis, '2026-09-21', 'Lemari dapur', 'harus bertanggal hari Kamis'],
            [JenisPenugasan::Minggu, '2026-09-21', 'Garasi', 'harus bertanggal hari Minggu'],
            [JenisPenugasan::Kamis, '2026-09-24', null, 'Pilih tugasnya'],
            [JenisPenugasan::Minggu, '2026-11-29', 'Garasi', 'tidak boleh setelah hari ini'],
            [JenisPenugasan::Kamis, '2026-09-24', 'Memasak nasi', 'bukan tugas Kamis/Minggu'],
        ];

        foreach ($kasus as [$jenis, $tanggal, $tugas, $pesan]) {
            try {
                $this->layanan()->tambah($u, $jenis, $this->tgl($tanggal), $tugas ? Tugas::query()->where('nama', $tugas)->firstOrFail() : null);
                $this->fail("Seharusnya ditolak: {$pesan}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString($pesan, collect($e->errors())->flatten()->first());
            }
        }

        $this->assertSame(0, Penugasan::query()->count());
    }

    public function test_satu_tugas_per_minggu_dan_satu_orang_per_tanggal_harian(): void
    {
        $this->layanan()->tambah($this->warga('Pika', 3), JenisPenugasan::Minggu, $this->tgl('2026-09-20'), $this->tugas('Garasi'));
        $this->layanan()->tambah($this->warga('Pipi', 2), JenisPenugasan::Minggu, $this->tgl('2026-09-27'), $this->tugas('Garasi'));
        $this->layanan()->tambah($this->warga('Adel', 3), JenisPenugasan::Kamis, $this->tgl('2026-09-24'), $this->tugas('Garasi')); // jenis lain, boleh

        try {
            $this->layanan()->tambah($this->warga('Ping', 2), JenisPenugasan::Minggu, $this->tgl('2026-09-20'), $this->tugas('Garasi'));
            $this->fail('Garasi Minggu 20 Sep sudah tercatat.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah tercatat untuk Pika', collect($e->errors())->flatten()->first());
        }

        $this->layanan()->tambah($this->warga('Dewi', 1), JenisPenugasan::Harian, $this->tgl('2026-09-14'));
        $this->expectException(ValidationException::class);
        $this->layanan()->tambah($this->warga('Septi', 1), JenisPenugasan::Harian, $this->tgl('2026-09-14'));
    }

    public function test_hanya_bintang_manual_yang_belum_dijadwalkan_yang_bisa_dihapus(): void
    {
        $u = $this->warga('Kia', 2);
        $manual = $this->layanan()->tambah($u, JenisPenugasan::Minggu, $this->tgl('2026-09-27'), $this->tugas('Jemuran'));
        $this->layanan()->hapus($manual);
        $this->assertModelMissing($manual);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'bintang.manual_dihapus')->exists());

        $otomatis = $this->piket($this->mingguTerbit(), $u, JenisPenugasan::Kamis, '2026-11-19', StatusPenugasan::Bintang, 'WC 1');
        $this->expectException(ValidationException::class);
        $this->layanan()->hapus($otomatis);
    }

    public function test_halaman_bintang_mencatat_dan_menampilkan_utang(): void
    {
        $this->masukSebagaiPengurus();
        $ping = $this->warga('Ping', 2);

        $this->get(route('pengurus.bintang'))->assertOk()->assertSee('Tambah bintang')->assertSee('Tidak ada bintang yang belum lunas');

        Livewire::test(Bintang::class)
            ->set('wargaId', $ping->id)
            ->set('jenis', 'minggu')
            ->assertSee('Tugas yang terlewat')
            ->set('tugasId', $this->tugas('Kaca depan')->id)
            ->set('tanggal', '2026-09-20')
            ->call('tambah')
            ->assertHasNoErrors()
            ->assertDispatched('toast', pesan: 'Bintang Ping dicatat.', jenis: 'sukses')
            ->assertSet('tugasId', '')
            ->assertSet('tanggal', '2026-09-20')
            ->assertSee('Kaca depan')
            ->assertSee('Manual')
            ->assertSee('Menunggu dijadwalkan')
            ->set('tanggal', '2026-09-21')
            ->set('tugasId', $this->tugas('Garasi')->id)
            ->call('tambah')
            ->assertDispatched('toast', jenis: 'gagal');

        $this->assertSame(1, Penugasan::query()->where('user_id', $ping->id)->count());
        $this->get(route('pengurus.pengawasan'))->assertSee('Bintang');
    }

    public function test_warga_biasa_tidak_bisa_membuka_halaman_bintang(): void
    {
        $this->actingAs($this->warga('Niya', 3));
        $this->get(route('pengurus.bintang'))->assertForbidden();
    }
}
