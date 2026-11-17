<?php

namespace Tests\Unit;

use App\Models\JadwalKuliah;
use App\Models\MingguPiket;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Models\User;
use App\Services\DrafHarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan B2: saran pembagian hari piket harian.
 */
class DrafHarianTest extends TestCase
{
    use RefreshDatabase;

    private MingguPiket $minggu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->minggu = MingguPiket::create(['tanggal_senin' => '2026-11-16', 'geng_harian_id' => $this->geng(1)->id]);
    }

    private function warga(string $nama, int $geng = 1): User
    {
        return User::factory()->create(['nama' => $nama, 'geng_id' => $this->geng($geng)->id]);
    }

    /** @param  array<int, string>  $jadwal  hari ISO => jam mulai */
    private function kuliah(User $u, array $jadwal): void
    {
        foreach ($jadwal as $hari => $mulai) {
            JadwalKuliah::create(['user_id' => $u->id, 'hari' => $hari, 'jam_mulai' => $mulai, 'jam_selesai' => '15:00']);
        }
    }

    private function hariDari(array $jadwal, User $u): ?int
    {
        $tgl = array_search($u->id, $jadwal, true);

        return $tgl === false ? null : \Carbon\CarbonImmutable::parse($tgl)->dayOfWeekIso;
    }

    public function test_tidak_pernah_menaruh_warga_di_hari_kuliah_sebelum_batas(): void
    {
        $orang = [];
        foreach (range(1, 6) as $i) {
            $orang[$i] = $this->warga("W{$i}");
            // Kuliah pagi jam 08.00 di semua hari kecuali hari ke-$i.
            $this->kuliah($orang[$i], collect(range(1, 7))->reject(fn ($h) => $h === $i)->mapWithKeys(fn ($h) => [$h => '08:00'])->all());
        }

        $hasil = app(DrafHarian::class)->susun($this->minggu);

        foreach ($orang as $i => $u) {
            $this->assertSame($i, $this->hariDari($hasil['jadwal'], $u), "{$u->nama} harus di hari {$i}");
        }
        $this->assertNull($hasil['jadwal']['2026-11-22'], 'Hari Minggu kosong');
        $this->assertSame([], $hasil['peringatan']);
    }

    public function test_kuliah_tepat_jam_batas_dianggap_bisa_dan_batas_bisa_diubah(): void
    {
        $u = $this->warga('Tepat');
        $this->kuliah($u, [1 => '09:00', 2 => '08:00', 3 => '08:00', 4 => '08:00', 5 => '08:00', 6 => '08:00', 7 => '08:00']);

        $this->assertSame([1], app(DrafHarian::class)->hariBisa(collect([$u]))[$u->id]);

        Pengaturan::simpan('batas_jam_kuliah', '08:00');
        $this->assertSame(range(1, 7), app(DrafHarian::class)->hariBisa(collect([$u]))[$u->id]);
    }

    public function test_maksimal_satu_hari_per_orang_dan_sisa_hari_kosong(): void
    {
        foreach (['A', 'B', 'C'] as $n) {
            $this->warga($n);
        }
        $this->warga('Geng lain', 2);

        $hasil = app(DrafHarian::class)->susun($this->minggu);

        $terisi = array_filter($hasil['jadwal']);
        $this->assertCount(3, $terisi);
        $this->assertSame(3, count(array_unique($terisi)));
        $this->assertCount(7, $hasil['jadwal']);
    }

    public function test_yang_paling_sedikit_hari_bisa_diisi_lebih_dulu(): void
    {
        // Leluasa bisa semua hari, Sempit hanya bisa Senin. Leluasa dibuat lebih dulu (id lebih kecil).
        $leluasa = $this->warga('Leluasa');
        $sempit = $this->warga('Sempit');
        $this->kuliah($sempit, [2 => '07:30', 3 => '07:30', 4 => '07:30', 5 => '07:30', 6 => '07:30', 7 => '07:30']);

        $hasil = app(DrafHarian::class)->susun($this->minggu);

        $this->assertSame(1, $this->hariDari($hasil['jadwal'], $sempit));
        $this->assertNotSame(1, $this->hariDari($hasil['jadwal'], $leluasa));
    }

    public function test_utamakan_hari_berbeda_dari_giliran_sebelumnya(): void
    {
        $u = $this->warga('Rani');
        $lalu = MingguPiket::create(['tanggal_senin' => '2026-10-26', 'geng_harian_id' => $this->geng(1)->id]);
        Penugasan::create([
            'minggu_piket_id' => $lalu->id, 'user_id' => $u->id, 'jenis' => 'harian',
            'tanggal' => '2026-10-26', 'tenggat' => '2026-10-26 09:00:00', 'status' => 'selesai',
        ]); // giliran sebelumnya hari Senin

        $hasil = app(DrafHarian::class)->susun($this->minggu);

        $this->assertNotSame(1, $this->hariDari($hasil['jadwal'], $u));
    }

    public function test_peringatan_untuk_warga_yang_tidak_bisa_di_hari_mana_pun(): void
    {
        $u = $this->warga('Pagi Terus');
        $this->kuliah($u, array_fill_keys(range(1, 7), '07:00'));

        $hasil = app(DrafHarian::class)->susun($this->minggu);

        $this->assertNull($this->hariDari($hasil['jadwal'], $u));
        $this->assertCount(1, $hasil['peringatan']);
        $this->assertStringContainsString('Pagi Terus', $hasil['peringatan'][0]);
    }

    public function test_lebih_dari_7_anggota_sisanya_masuk_peringatan(): void
    {
        foreach (range(1, 9) as $i) {
            $this->warga("W{$i}");
        }

        $hasil = app(DrafHarian::class)->susun($this->minggu);

        $this->assertCount(7, array_filter($hasil['jadwal']));
        $this->assertCount(2, $hasil['peringatan']);
    }

    public function test_simpan_membuat_penugasan_tenggat_jam_9_dan_tidak_menimpa_bayar_bintang(): void
    {
        $a = $this->warga('A');
        $bayar = $this->warga('Pembayar', 2);
        Penugasan::create([
            'minggu_piket_id' => $this->minggu->id, 'user_id' => $bayar->id, 'jenis' => 'harian',
            'tanggal' => '2026-11-16', 'tenggat' => '2026-11-16 09:00:00', 'status' => 'bayar_bintang',
        ]);

        app(DrafHarian::class)->simpan($this->minggu);
        app(DrafHarian::class)->simpan($this->minggu); // menyusun ulang tidak menggandakan

        $baris = $this->minggu->penugasan()->where('user_id', $a->id)->get();
        $this->assertCount(1, $baris);
        $this->assertNotSame('2026-11-16', $baris[0]->tanggal->toDateString());
        $this->assertSame('09:00', $baris[0]->tenggat->format('H:i'));
        $this->assertSame($baris[0]->tanggal->toDateString(), $baris[0]->tenggat->toDateString());
        $this->assertSame('bayar_bintang', Penugasan::query()->where('user_id', $bayar->id)->first()->status->value);
    }
}
