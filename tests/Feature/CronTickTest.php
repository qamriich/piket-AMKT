<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPenugasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * PRD bagian 9: GET /cron/tick?key=<CRON_SECRET> menjalankan tugas berkala dan mengirim antrean email.
 */
class CronTickTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
        $this->waktu('2026-11-18 12:00'); // Rabu
        config(['piket.cron_secret' => 'rahasia-uji']);
    }

    public function test_tanpa_secret_atau_secret_salah_dianggap_tidak_ada(): void
    {
        $this->get('/cron/tick')->assertNotFound();
        $this->get('/cron/tick?key=salah')->assertNotFound();

        config(['piket.cron_secret' => '']);
        $this->get('/cron/tick?key=')->assertNotFound();
    }

    public function test_tick_memberi_bintang_untuk_piket_yang_lewat_tenggat(): void
    {
        $m = $this->mingguTerbit();
        $p = $this->piket($m, $this->warga('Gita', 1), JenisPenugasan::Harian, '2026-11-17'); // Selasa, sudah lewat

        $this->get('/cron/tick?key=rahasia-uji')
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('piket', fn ($teks) => str_contains($teks, 'Bintang baru: 1'));

        $this->assertSame(StatusPenugasan::Bintang, $p->fresh()->status);
    }

    public function test_tick_mengirim_antrean_lalu_berhenti_saat_kosong(): void
    {
        config(['queue.default' => 'database']);
        dispatch(fn () => Cache::put('tick-uji', 'terkirim'));
        $this->assertSame(1, DB::table('jobs')->count());

        $this->get('/cron/tick?key=rahasia-uji')->assertOk();

        $this->assertSame('terkirim', Cache::get('tick-uji'));
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_tick_tidak_membuat_sesi_atau_cookie(): void
    {
        $this->get('/cron/tick?key=rahasia-uji')->assertOk()->assertCookieMissing(config('session.cookie'));
    }

    public function test_tick_yang_bertumpuk_tidak_berjalan_dua_kali(): void
    {
        $kunci = Cache::lock('cron-tick', 300);
        $kunci->get();

        $this->get('/cron/tick?key=rahasia-uji')->assertOk()->assertJsonPath('pesan', 'Tick sebelumnya masih berjalan.');

        $kunci->release();
    }
}
