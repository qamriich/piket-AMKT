<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Livewire\Warga\Utang;
use App\Models\Izin;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Models\PeriodeHarian;
use App\Models\Tugas;
use App\Models\User;
use App\Services\GantiBintang;
use App\Services\JadwalMinggu;
use App\Services\PenugasanOtomatis;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Perintah php artisan app:data-contoh (khusus lokal, bisa dijalankan berulang).
 */
class DataContohTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00', 'Asia/Makassar')); // Rabu, minggu 16 Nov = Geng 1

        // Kamis minggu ini sudah dibagi dan terbit, seperti di data lokal.
        $jadwal = app(JadwalMinggu::class);
        $minggu = $jadwal->siapkan(now());
        app(PenugasanOtomatis::class)->bagikan($minggu, SesiTugas::Kamis);
        // Pastikan Adel punya tugas Kamis; pemegang lamanya jadi bebas sehingga ada calon pengganti.
        $tugasPertama = Tugas::query()->whereIn('id', $minggu->tugasAktif()->where('sesi', 'kamis')->select('tugas_id'))->orderBy('urutan')->firstOrFail();
        $minggu->penugasan()->where('user_id', $this->warga('Adel')->id)->delete();
        $jadwal->aturPetugasTugas($minggu, SesiTugas::Kamis, $tugasPertama, $this->warga('Adel'));
        $jadwal->terbitkan($minggu->fresh(), 'kamis');
        Notification::fake(); // abaikan email penerbitan
    }

    private function warga(string $nama): User
    {
        return User::query()->where('nama', $nama)->firstOrFail();
    }

    public function test_membuat_semua_data_contoh_dan_tidak_dobel_kalau_diulang(): void
    {
        $this->artisan('app:data-contoh')->assertSuccessful();
        $hitung = fn () => [Penugasan::query()->count(), Izin::query()->count(), PeriodeHarian::query()->count(), PengajuanGantiBintang::query()->count()];
        $sebelum = $hitung();

        $this->artisan('app:data-contoh')->assertSuccessful();
        $this->assertSame($sebelum, $hitung(), 'Dijalankan ulang tidak menambah data');

        // Ping: 1 bintang harian minggu lalu, belum lunas, belum diajukan.
        $ping = $this->warga('Ping');
        $bintangPing = Penugasan::query()->bintangBelumLunas()->where('user_id', $ping->id)->sole();
        $this->assertSame(JenisPenugasan::Harian, $bintangPing->jenis);
        $this->assertSame('2026-11-10', $bintangPing->tanggal->toDateString());
        $this->assertFalse($bintangPing->pengajuanGantiBintang()->exists());

        // Eca: bintang Kamis minggu lalu.
        $eca = Penugasan::query()->bintangBelumLunas()->where('user_id', $this->warga('Eca')->id)->sole();
        $this->assertSame([JenisPenugasan::Kamis, '2026-11-12'], [$eca->jenis, $eca->tanggal->toDateString()]);

        // Dilla: bintang harian yang sudah lunas, dibayar dengan piket Selesai.
        $dilla = Penugasan::query()->where('user_id', $this->warga('Dilla')->id)->where('status', 'lunas')->sole();
        $this->assertSame(StatusPenugasan::Selesai, $dilla->pembayaran()->sole()->status);

        // Hari kosong di 4 minggu ke depan untuk Ping.
        $kosong = app(GantiBintang::class)->tanggalKosong()
            ->filter(fn (CarbonImmutable $t) => $t->lt(CarbonImmutable::parse('2026-12-21', 'Asia/Makassar')));
        $this->assertGreaterThanOrEqual(6, $kosong->count());
        $this->assertSame('Data contoh', PeriodeHarian::query()->sole()->nama);

        // Adel: izin Kamis 07.00 – Jumat 20.00, piket Kamisnya jadi Izin dengan pengganti otomatis.
        $adel = $this->warga('Adel');
        $izin = Izin::query()->where('user_id', $adel->id)->sole();
        $this->assertSame(['2026-11-19 07:00', '2026-11-20 20:00'], [$izin->mulai->format('Y-m-d H:i'), $izin->selesai->format('Y-m-d H:i')]);
        $piketAdel = Penugasan::query()->where('user_id', $adel->id)->where('jenis', 'kamis')->sole();
        $this->assertSame(StatusPenugasan::Izin, $piketAdel->status);
        $pengganti = Penugasan::query()->bukanIzin()->where('jenis', 'kamis')->where('minggu_piket_id', $piketAdel->minggu_piket_id)
            ->where('tugas_id', $piketAdel->tugas_id)->sole();
        $this->assertNotSame($adel->id, $pengganti->user_id);
    }

    public function test_tampilan_warga_dari_data_contoh(): void
    {
        $this->artisan('app:data-contoh')->assertSuccessful();

        $this->actingAs($this->warga('Ping'));
        // Tombol "Ajukan ganti" (bukan teks penjelasan di atas halaman) dan pilihan hari kosong.
        Livewire::test(Utang::class)->assertSeeHtml('@click="buka = true"')->assertSee('Rabu');

        $this->actingAs($this->warga('Eca'));
        Livewire::test(Utang::class)->assertSee('Akan dijadwalkan ulang di Kamis berikutnya')->assertDontSeeHtml('@click="buka = true"');

        $this->actingAs($this->warga('Dilla'));
        Livewire::test(Utang::class)->assertSee('Riwayat bintang lunas')->assertSee('Sudah dibayar');

        $wilda = $this->warga('Wilda');
        $wilda->forceFill(['harus_ganti_password' => false])->save(); // akun seeder wajib ganti password dulu (R2)
        $this->actingAs($wilda);
        $this->get(route('pengurus.pengawasan'))->assertSee('Sedang izin minggu ini')->assertSee('Adel');
    }

    public function test_pengajuan_ping_direset_saat_diulang(): void
    {
        $this->artisan('app:data-contoh')->assertSuccessful();
        $ping = $this->warga('Ping');
        $bintang = Penugasan::query()->bintangBelumLunas()->where('user_id', $ping->id)->sole();
        app(GantiBintang::class)->ajukan($ping, $bintang, app(GantiBintang::class)->tanggalKosong()->first());

        $this->artisan('app:data-contoh')->assertSuccessful();

        $this->assertSame(0, PengajuanGantiBintang::query()->count());
    }

    public function test_tidak_boleh_jalan_di_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('app:data-contoh')->assertFailed();
        $this->assertSame(0, Izin::query()->count());
        $this->assertSame(0, Penugasan::query()->where('status', 'bintang')->count());
        $this->assertNotNull(Tugas::query()->first());
    }
}
