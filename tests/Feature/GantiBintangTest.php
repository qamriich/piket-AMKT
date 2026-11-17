<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Livewire\Pengurus\Pengajuan;
use App\Livewire\Warga\Utang;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Models\User;
use App\Notifications\PengajuanBaru;
use App\Notifications\PengajuanDiputuskan;
use App\Services\GantiBintang;
use App\Services\JadwalHarianPeriode;
use App\Services\PelaksanaanPiket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * F8: pengajuan ganti bintang harian di tanggal kosong, dan pelunasannya (B5).
 */
class GantiBintangTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
        $this->waktu('2026-11-18 10:00'); // Rabu
    }

    private function tanggal(string $t): CarbonImmutable
    {
        return CarbonImmutable::parse($t, config('app.timezone'));
    }

    /** Minggu ini terbit: Kamis 19 dan Sabtu 21 terisi, Jumat 20, Minggu 22 kosong. Ping punya 1 bintang harian. */
    private function siapkan(): array
    {
        $m = $this->mingguTerbit();
        $ping = $this->warga('Ping', 1);
        $lain = $this->warga('Lain', 1);
        $this->piket($m, $lain, JenisPenugasan::Harian, '2026-11-19');
        $this->piket($m, $lain, JenisPenugasan::Harian, '2026-11-21');
        $bintang = $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-17', StatusPenugasan::Bintang);

        return [$m, $ping, $bintang];
    }

    public function test_hanya_tanggal_yang_benar_benar_kosong(): void
    {
        [, $ping, $bintang] = $this->siapkan();
        $kosong = fn () => app(GantiBintang::class)->tanggalKosong()->map->toDateString()->all();

        $this->assertSame(['2026-11-20', '2026-11-22'], $kosong());

        // Tanggal yang sedang diajukan orang lain tidak ditampilkan lagi.
        app(GantiBintang::class)->ajukan($ping, $bintang, $this->tanggal('2026-11-20'));
        $this->assertSame(['2026-11-22'], $kosong());
    }

    public function test_hari_kosong_karena_izin_bisa_dipakai_ganti(): void
    {
        [$m] = $this->siapkan();
        Penugasan::query()->whereDate('tanggal', '2026-11-21')->update(['status' => StatusPenugasan::Izin->value]);

        $this->assertContains('2026-11-21', app(GantiBintang::class)->tanggalKosong()->map->toDateString()->all());
    }

    public function test_alur_lengkap_ajukan_terima_lalu_lunas(): void
    {
        [, $ping, $bintang] = $this->siapkan();
        $wilda = $this->masukSebagaiPengurus();
        $this->actingAs($ping);

        $this->get(route('warga.utang'))->assertSee('Ajukan ganti')->assertSee('Jumat')->assertDontSee('segera tersedia');
        Livewire::test(Utang::class)->call('ajukan', $bintang->id, '2026-11-20')->assertDispatched('toast', jenis: 'sukses')
            ->assertSee('Pengajuan terkirim');

        $ajuan = PengajuanGantiBintang::query()->sole();
        $this->assertSame(StatusPengajuan::Menunggu, $ajuan->status);
        Notification::assertSentTo($wilda, PengajuanBaru::class, fn ($n) => str_contains($n->teks(), 'Ping'));

        $this->actingAs($wilda);
        Livewire::test(Pengajuan::class)->assertSee('Terima')->call('terimaPengajuan', $ajuan->id, 'Jangan telat ya')->assertDispatched('toast', jenis: 'sukses');

        $bayar = Penugasan::query()->where('status', 'bayar_bintang')->sole();
        $this->assertSame([$ping->id, '2026-11-20', $bintang->id], [$bayar->user_id, $bayar->tanggal->toDateString(), $bayar->membayar_penugasan_id]);
        $this->assertSame(StatusPengajuan::Diterima, $ajuan->fresh()->status);
        $this->assertNotContains('2026-11-20', app(GantiBintang::class)->tanggalKosong()->map->toDateString()->all());
        Notification::assertSentTo($ping, PengajuanDiputuskan::class, fn ($n) => str_contains($n->teks(), 'diterima') && str_contains($n->teks(), 'Jangan telat ya'));

        // Setelah piket gantinya selesai, bintangnya lunas.
        $this->waktu('2026-11-20 06:00');
        app(PelaksanaanPiket::class)->tandaiSelesai($bayar, $ping);
        $this->assertSame(StatusPenugasan::Lunas, $bintang->fresh()->status);
    }

    public function test_tolak_dengan_catatan(): void
    {
        [, $ping, $bintang] = $this->siapkan();
        $ajuan = app(GantiBintang::class)->ajukan($ping, $bintang, $this->tanggal('2026-11-22'));

        $this->masukSebagaiPengurus();
        Livewire::test(Pengajuan::class)->call('tolakPengajuan', $ajuan->id, 'Hari Minggu ada kerja bakti');

        $this->assertSame(StatusPengajuan::Ditolak, $ajuan->fresh()->status);
        $this->assertSame('Hari Minggu ada kerja bakti', $ajuan->fresh()->catatan);
        $this->assertSame(0, Penugasan::query()->where('status', 'bayar_bintang')->count());
        Notification::assertSentTo($ping, PengajuanDiputuskan::class, fn ($n) => str_contains($n->teks(), 'ditolak') && str_contains($n->teks(), 'kerja bakti'));
    }

    public function test_pengajuan_yang_tidak_sah_ditolak(): void
    {
        [$m, $ping, $bintang] = $this->siapkan();
        $layanan = app(GantiBintang::class);
        $kamis = $this->piket($m, $ping, JenisPenugasan::Kamis, '2026-11-12', StatusPenugasan::Bintang, 'WC 1');
        $orangLain = $this->warga('Orang lain', 2);

        foreach ([
            fn () => $layanan->ajukan($ping, $bintang, $this->tanggal('2026-11-19')),       // sudah terisi
            fn () => $layanan->ajukan($ping, $bintang, $this->tanggal('2026-11-18')),       // hari ini
            fn () => $layanan->ajukan($ping, $kamis, $this->tanggal('2026-11-20')),         // bukan bintang harian
            fn () => $layanan->ajukan($orangLain, $bintang, $this->tanggal('2026-11-20')),  // bukan miliknya
        ] as $i => $aksi) {
            try {
                $aksi();
                $this->fail("Kasus {$i} seharusnya ditolak");
            } catch (ValidationException) {
            }
        }

        $layanan->ajukan($ping, $bintang, $this->tanggal('2026-11-20'));
        $this->expectException(ValidationException::class);
        $layanan->ajukan($ping, $bintang, $this->tanggal('2026-11-22')); // masih menunggu
    }

    public function test_terima_gagal_kalau_tanggal_sudah_terisi(): void
    {
        [$m, $ping, $bintang] = $this->siapkan();
        $ajuan = app(GantiBintang::class)->ajukan($ping, $bintang, $this->tanggal('2026-11-20'));
        $this->piket($m, $this->warga('Pengganti', 1), JenisPenugasan::Harian, '2026-11-20');

        $this->masukSebagaiPengurus();
        Livewire::test(Pengajuan::class)->call('terimaPengajuan', $ajuan->id)->assertDispatched('toast', jenis: 'gagal');
        $this->assertSame(StatusPengajuan::Menunggu, $ajuan->fresh()->status);
    }

    public function test_r6_hari_kosong_minggu_depan_dihitung_dari_jadwal_periode(): void
    {
        [, $ping, $bintang] = $this->siapkan();
        $septi = $this->warga('Septi', 2);
        $periode = app(JadwalHarianPeriode::class);
        $p = $periode->simpanPeriode(null, 'Ganjil', $this->tanggal('2026-11-01'), $this->tanggal('2026-11-30'));
        // Minggu 23 Nov: rotasi B1 = Geng 2. Hanya Senin yang diisi; hari lain sampai akhir periode kosong.
        $periode->simpanHari($p, [$this->geng(2)->id => [1 => $septi->id]]);

        $kosong = app(GantiBintang::class)->tanggalKosong()->map->toDateString()->all();

        $this->assertNotContains('2026-11-23', $kosong, 'Senin terisi Septi menurut periode');
        $this->assertContains('2026-11-24', $kosong);
        $this->assertContains('2026-11-30', $kosong);
        $this->assertNotContains('2026-12-01', $kosong, 'Di luar periode: jadwal harian belum ada');

        // Diterima untuk minggu yang barisnya belum dibuat: minggunya dibuat dan tanggal itu terisi Bayar Bintang.
        $ajuan = app(GantiBintang::class)->ajukan($ping, $bintang, $this->tanggal('2026-11-25'));
        app(GantiBintang::class)->terima($ajuan, $this->masukSebagaiPengurus());
        $this->assertSame(StatusPenugasan::BayarBintang, Penugasan::query()->whereDate('tanggal', '2026-11-25')->sole()->status);
        $this->assertNotContains('2026-11-25', app(GantiBintang::class)->tanggalKosong()->map->toDateString()->all());
    }

    public function test_r6_kartu_bintang_menampilkan_status_pengajuan(): void
    {
        [$m, $ping, $bintang] = $this->siapkan();
        $kamis = $this->piket($m, $ping, JenisPenugasan::Kamis, '2026-11-12', StatusPenugasan::Bintang, 'WC 1');
        $lunas = $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-16', StatusPenugasan::Lunas);
        $this->actingAs($ping);

        // Bintang Kamis tanpa tombol; bintang harian dengan tombol; yang lunas di riwayat bawah.
        Livewire::test(Utang::class)
            ->assertSeeInOrder(['Ajukan ganti', 'Akan dijadwalkan ulang di Kamis berikutnya', 'Riwayat bintang lunas'])
            ->assertSee('Minggu 16–22 Nov');

        $ajuan = app(GantiBintang::class)->ajukan($ping, $bintang, $this->tanggal('2026-11-20'));
        Livewire::test(Utang::class)->assertSee('Pengajuan terkirim')->assertSee('Menunggu')->assertDontSee('Ajukan ganti</');

        $pengurus = User::factory()->pengurus()->create();
        app(GantiBintang::class)->tolak($ajuan, $pengurus, 'Hari itu ada acara');
        Livewire::test(Utang::class)->assertSee('Pengajuan ditolak')->assertSee('Hari itu ada acara')->assertSee('Ajukan ganti');

        $ajuan2 = app(GantiBintang::class)->ajukan($ping, $bintang->fresh(), $this->tanggal('2026-11-22'));
        app(GantiBintang::class)->terima($ajuan2, $pengurus);
        Livewire::test(Utang::class)->assertSee('Pengajuan diterima')->assertSee('Diterima');

        $this->assertNotNull($kamis);
        $this->assertNotNull($lunas);
    }
}
