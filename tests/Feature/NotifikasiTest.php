<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Livewire\Pengurus\Rekap;
use App\Models\User;
use App\Notifications\JadwalBerubah;
use App\Notifications\JadwalDiterbitkan;
use App\Notifications\JadwalHarianMingguIni;
use App\Notifications\PemberitahuanPiket;
use App\Notifications\PengingatPiket;
use App\Services\JadwalMinggu;
use App\Services\Pemberitahuan;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * Notifikasi email (PRD bagian 8) dan B8 (perubahan setelah terbit).
 */
class NotifikasiTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
        $this->waktu('2026-11-17 10:00'); // Selasa
    }

    public function test_menerbitkan_kamis_mengirim_email_ke_semua_warga_aktif(): void
    {
        $m = $this->mingguTerbit(status: ['status_kamis' => 'draf'], aktif: ['kamis' => ['WC 1']]);
        $nivia = $this->warga('Nivia', 2);
        $bebas = $this->warga('Bebas', 3);
        $harian = $this->warga('Harian', 1);
        $nonaktif = $this->warga('Nonaktif', 2, ['aktif' => false]);
        $this->piket($m, $nivia, JenisPenugasan::Kamis, tugas: 'WC 1');

        app(JadwalMinggu::class)->terbitkan($m, 'kamis');

        Notification::assertSentTo($nivia, JadwalDiterbitkan::class, fn ($n) => str_contains($n->teks(), 'WC 1') && str_contains($n->teks(), 'batas Jumat'));
        Notification::assertSentTo($bebas, JadwalDiterbitkan::class, fn ($n) => str_contains($n->teks(), 'bebas'));
        Notification::assertSentTo($harian, JadwalDiterbitkan::class, fn ($n) => str_contains($n->teks(), 'piket harian'));
        Notification::assertNotSentTo($nonaktif, JadwalDiterbitkan::class);
        $this->assertNotNull($m->penugasan()->first()->diberitahu_pada);
    }

    public function test_perubahan_setelah_terbit_hanya_ke_yang_terdampak(): void
    {
        $m = $this->mingguTerbit(aktif: ['kamis' => ['WC 1', 'WC 2']]);
        [$a, $b, $c, $d] = array_map(fn ($n) => $this->warga($n, 2), ['A', 'B', 'C', 'D']);
        $p1 = $this->piket($m, $a, JenisPenugasan::Kamis, tugas: 'WC 1');
        $p2 = $this->piket($m, $b, JenisPenugasan::Kamis, tugas: 'WC 2');
        $jadwal = app(JadwalMinggu::class);

        $jadwal->aturPetugasTugas($m, SesiTugas::Kamis, $this->tugas('WC 1'), $c);
        Notification::assertSentTo($a, JadwalBerubah::class, fn ($n) => str_contains($n->teks(), 'tidak lagi'));
        Notification::assertSentTo($c, JadwalBerubah::class, fn ($n) => str_contains($n->teks(), 'WC 1'));
        Notification::assertNotSentTo([$b, $d], JadwalBerubah::class);

        $jadwal->tukar($p1->fresh(), $p2->fresh());
        Notification::assertSentTo($b, JadwalBerubah::class, fn ($n) => str_contains($n->teks(), 'Sekarang: WC 1'));
        Notification::assertSentToTimes($c, JadwalBerubah::class, 2);
        Notification::assertNotSentTo($d, JadwalBerubah::class);
    }

    public function test_perubahan_draf_tidak_mengirim_email(): void
    {
        $m = $this->mingguTerbit(status: ['status_kamis' => 'draf'], aktif: ['kamis' => ['WC 1']]);
        $a = $this->warga('A', 2);

        app(JadwalMinggu::class)->aturPetugasTugas($m, SesiTugas::Kamis, $this->tugas('WC 1'), $a);

        Notification::assertNothingSent();
    }

    public function test_email_piket_harian_minggu_ini_sekali_saja(): void
    {
        $m = $this->mingguTerbit();
        $ping = $this->warga('Ping', 1);
        $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-16'); // sudah lewat
        $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-19');

        $this->assertSame(1, app(Pemberitahuan::class)->harianMingguIni());
        $this->assertSame(0, app(Pemberitahuan::class)->harianMingguIni());
        $this->actingAs($ping)->get(route('dashboard'))->assertOk();

        Notification::assertSentToTimes($ping, JadwalHarianMingguIni::class, 1);
        Notification::assertSentTo($ping, JadwalHarianMingguIni::class, fn ($n) => str_contains($n->subjek, 'Kamis, 19 Nov') && ! str_contains($n->teks(), '16 Nov'));
    }

    public function test_pengingat_sesuai_jadwal_dan_sekali_saja(): void
    {
        $m = $this->mingguTerbit();
        $ping = $this->warga('Ping', 1);
        $gita = $this->warga('Gita', 2);
        $dewi = $this->warga('Dewi', 2);
        $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-19');
        $this->piket($m, $gita, JenisPenugasan::Kamis, tugas: 'WC 1');
        $this->piket($m, $dewi, JenisPenugasan::Kamis, tugas: 'WC 2', status: StatusPenugasan::Selesai);
        $layanan = app(Pemberitahuan::class);
        $pada = fn (string $t) => $layanan->pengingat(CarbonImmutable::parse($t, config('app.timezone')));

        // Harian: malam sebelumnya jam 20.00.
        $this->assertSame(0, $pada('2026-11-18 19:59'));
        $this->assertSame(1, $pada('2026-11-18 20:00'));
        $this->assertSame(0, $pada('2026-11-18 20:15'));
        Notification::assertSentTo($ping, PengingatPiket::class, fn ($n) => str_contains($n->teks(), 'besok pagi'));

        // Kamis: Jumat jam 12.00. Yang sudah selesai tidak diingatkan.
        $this->assertSame(0, $pada('2026-11-20 11:45'));
        $this->assertSame(1, $pada('2026-11-20 12:00'));
        Notification::assertSentTo($gita, PengingatPiket::class);
        Notification::assertNotSentTo($dewi, PengingatPiket::class);
    }

    public function test_pengingat_minggu_senin_jam_12(): void
    {
        $m = $this->mingguTerbit();
        $u = $this->warga('A', 2);
        $this->piket($m, $u, JenisPenugasan::KerjaBakti);

        $this->assertSame(0, app(Pemberitahuan::class)->pengingat(CarbonImmutable::parse('2026-11-23 11:59', config('app.timezone'))));
        $this->assertSame(1, app(Pemberitahuan::class)->pengingat(CarbonImmutable::parse('2026-11-23 12:00', config('app.timezone'))));
    }

    public function test_email_lewat_antrean_berbahasa_indonesia_dan_ada_link(): void
    {
        $n = new JadwalBerubah('Piketmu berubah', ['Isi'], 'Lihat piket saya', 'warga.piket');
        $this->assertInstanceOf(ShouldQueue::class, $n);
        $this->assertInstanceOf(PemberitahuanPiket::class, $n);

        $mail = $n->toMail(User::factory()->make(['nama' => 'Nivia']));
        $this->assertSame('Halo, Nivia', $mail->greeting);
        $this->assertSame(route('warga.piket'), $mail->actionUrl);
        $this->assertStringContainsString('Pengurus AMKT', $mail->salutation);
    }

    public function test_ekspor_rekap_csv(): void
    {
        $m = $this->mingguTerbit();
        $ping = $this->warga('Ping', 1);
        $this->piket($m, $ping, JenisPenugasan::Harian, '2026-11-16', StatusPenugasan::Selesai);
        $this->masukSebagaiPengurus();

        $this->get(route('pengurus.rekap'))->assertSee('Ekspor CSV')->assertDontSee('segera tersedia');
        Livewire::test(Rekap::class, ['bulan' => '2026-11'])
            ->call('ekspor')
            ->assertFileDownloaded('rekap-piket-2026-11.csv');
    }
}
