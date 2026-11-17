<?php

namespace Tests\Unit;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Models\Penugasan;
use App\Notifications\BintangDiberikan;
use App\Services\BintangOtomatis;
use App\Services\PenugasanOtomatis;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * Aturan B4: bintang otomatis setelah tenggat lewat.
 */
class BintangOtomatisTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
    }

    public function test_piket_belum_selesai_menjadi_bintang_setelah_tenggat_dan_pemilik_mendapat_email(): void
    {
        $m = $this->mingguTerbit();
        $gita = $this->warga('Gita');
        $p = $this->piket($m, $gita, JenisPenugasan::Kamis, tugas: 'Pelataran'); // batas Jumat 20 Nov 18.00

        $this->waktu('2026-11-20 17:59');
        $this->assertSame(0, app(BintangOtomatis::class)->proses());
        $this->assertSame(StatusPenugasan::Terjadwal, $p->fresh()->status);

        $this->waktu('2026-11-20 18:01');
        $this->assertSame(1, app(BintangOtomatis::class)->proses());
        $this->assertSame(StatusPenugasan::Bintang, $p->fresh()->status);

        Notification::assertSentTo($gita, BintangDiberikan::class, fn (BintangDiberikan $n) => str_contains($n->teks(), 'Pelataran')
            && str_contains($n->teks(), 'dijadwalkan ulang'));
    }

    public function test_tenggat_tiap_jenis(): void
    {
        $m = $this->mingguTerbit();
        $u = $this->warga('A');
        $harian = $this->piket($m, $u, JenisPenugasan::Harian, '2026-11-18');
        $minggu = $this->piket($m, $u, JenisPenugasan::Minggu, tugas: 'WC 1');

        $this->waktu('2026-11-18 09:01');
        app(BintangOtomatis::class)->proses();
        $this->assertSame(StatusPenugasan::Bintang, $harian->fresh()->status, 'Harian: jam 09.00 hari itu');
        $this->assertSame(StatusPenugasan::Terjadwal, $minggu->fresh()->status);

        $this->waktu('2026-11-23 18:01');
        app(BintangOtomatis::class)->proses();
        $this->assertSame(StatusPenugasan::Bintang, $minggu->fresh()->status, 'Minggu: Senin jam 18.00');
    }

    public function test_selesai_izin_dan_draf_tidak_menjadi_bintang(): void
    {
        $m = $this->mingguTerbit(status: ['status_kamis' => 'draf']);
        $u = $this->warga('A');
        $selesai = $this->piket($m, $u, JenisPenugasan::Harian, '2026-11-17', StatusPenugasan::Selesai);
        $izin = $this->piket($m, $u, JenisPenugasan::Harian, '2026-11-18', StatusPenugasan::Izin);
        $draf = $this->piket($m, $u, JenisPenugasan::Kamis, tugas: 'WC 1');

        $this->waktu('2026-11-25 10:00');
        $this->assertSame(0, app(BintangOtomatis::class)->proses());

        $this->assertSame(StatusPenugasan::Selesai, $selesai->fresh()->status);
        $this->assertSame(StatusPenugasan::Izin, $izin->fresh()->status);
        $this->assertSame(StatusPenugasan::Terjadwal, $draf->fresh()->status, 'Draf belum dilihat warga');
        Notification::assertNothingSent();
    }

    public function test_bayar_bintang_terlewat_tidak_menambah_utang_dan_dijadwalkan_ulang(): void
    {
        $gita = $this->warga('Gita');
        $m1 = $this->mingguTerbit('2026-11-09');
        $asli = $this->piket($m1, $gita, JenisPenugasan::Kamis, status: StatusPenugasan::Bintang, tugas: 'Pelataran');

        $m2 = $this->mingguTerbit(aktif: ['kamis' => ['Pelataran']]);
        $bayar = $this->piket($m2, $gita, JenisPenugasan::Kamis, status: StatusPenugasan::BayarBintang, tugas: 'Pelataran', membayar: $asli);

        $this->waktu('2026-11-20 18:30');
        app(BintangOtomatis::class)->proses();

        $this->assertSame(StatusPenugasan::Bintang, $bayar->fresh()->status);
        $this->assertSame(StatusPenugasan::Bintang, $asli->fresh()->status, 'Piket aslinya tetap Bintang');
        $this->assertSame(1, Penugasan::query()->bintangBelumLunas()->where('user_id', $gita->id)->count(), 'Utang tetap 1');

        // Minggu berikutnya, bintang aslinya kembali dikunci sebagai Bayar Bintang.
        $m3 = $this->mingguTerbit('2026-11-23', ['status_kamis' => 'draf'], gengHarian: 1, aktif: ['kamis' => ['Pelataran']]);
        app(PenugasanOtomatis::class)->bagikan($m3, SesiTugas::Kamis);
        $baru = $m3->penugasan()->where('status', 'bayar_bintang')->first();
        $this->assertSame($asli->id, $baru?->membayar_penugasan_id);
    }

    public function test_tidak_diproses_dua_kali(): void
    {
        $m = $this->mingguTerbit();
        $u = $this->warga('A');
        $this->piket($m, $u, JenisPenugasan::Harian, '2026-11-18');

        $this->waktu('2026-11-18 10:00');
        $this->assertSame(1, app(BintangOtomatis::class)->proses());
        $this->assertSame(0, app(BintangOtomatis::class)->proses());
        Notification::assertSentToTimes($u, BintangDiberikan::class, 1);
    }

    public function test_pengecekan_ringan_saat_halaman_dibuka(): void
    {
        $m = $this->mingguTerbit();
        $u = $this->warga('A');
        $p = $this->piket($m, $u, JenisPenugasan::Harian, '2026-11-18');

        $this->waktu('2026-11-18 09:05');
        $this->actingAs($u)->get(route('warga.piket'))->assertOk()->assertSee('Bintang');
        $this->assertSame(StatusPenugasan::Bintang, $p->fresh()->status);
    }

    public function test_scheduler_berjalan_tiap_15_menit(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'piket:jalankan'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);

        $m = $this->mingguTerbit();
        $p = $this->piket($m, $this->warga('A'), JenisPenugasan::Harian, '2026-11-18');
        $this->waktu('2026-11-18 09:14');
        $this->artisan('piket:jalankan')->assertSuccessful();
        $this->assertSame(StatusPenugasan::Bintang, $p->fresh()->status);
    }
}
