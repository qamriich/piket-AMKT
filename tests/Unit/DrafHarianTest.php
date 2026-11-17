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
 * Aturan B2: saran pembagian hari piket harian, dipakai untuk draf jadwal harian periode per geng (R1).
 */
class DrafHarianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->travelTo(now()->setDate(2026, 11, 18));
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

    private function susun(): array
    {
        return app(DrafHarian::class)->susunUntukGeng($this->geng(1));
    }

    private function hariDari(array $jadwal, User $u): ?int
    {
        $hari = array_search($u->id, $jadwal, true);

        return $hari === false ? null : $hari;
    }

    public function test_tidak_pernah_menaruh_warga_di_hari_kuliah_sebelum_batas(): void
    {
        $orang = [];
        foreach (range(1, 6) as $i) {
            $orang[$i] = $this->warga("W{$i}");
            // Kuliah pagi jam 08.00 di semua hari kecuali hari ke-$i.
            $this->kuliah($orang[$i], collect(range(1, 7))->reject(fn ($h) => $h === $i)->mapWithKeys(fn ($h) => [$h => '08:00'])->all());
        }

        $hasil = $this->susun();

        foreach ($orang as $i => $u) {
            $this->assertSame($i, $this->hariDari($hasil['jadwal'], $u), "{$u->nama} harus di hari {$i}");
        }
        $this->assertNull($hasil['jadwal'][7], 'Hari Minggu kosong');
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

    public function test_hanya_anggota_aktif_geng_itu_maksimal_satu_hari_per_orang_dan_sisa_kosong(): void
    {
        foreach (['A', 'B', 'C'] as $n) {
            $this->warga($n);
        }
        $lain = $this->warga('Geng lain', 2);
        $nonaktif = User::factory()->nonaktif()->create(['geng_id' => $this->geng(1)->id]);

        $hasil = $this->susun();

        $terisi = array_filter($hasil['jadwal']);
        $this->assertCount(3, $terisi);
        $this->assertSame(3, count(array_unique($terisi)));
        $this->assertCount(7, $hasil['jadwal']);
        $this->assertNotContains($lain->id, $terisi);
        $this->assertNotContains($nonaktif->id, $terisi);
    }

    public function test_yang_paling_sedikit_hari_bisa_diisi_lebih_dulu(): void
    {
        $leluasa = $this->warga('Leluasa');
        $sempit = $this->warga('Sempit');
        $this->kuliah($sempit, [2 => '07:30', 3 => '07:30', 4 => '07:30', 5 => '07:30', 6 => '07:30', 7 => '07:30']);

        $hasil = $this->susun();

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

        $this->assertNotSame(1, $this->hariDari($this->susun()['jadwal'], $u));
    }

    public function test_peringatan_untuk_warga_yang_tidak_bisa_di_hari_mana_pun(): void
    {
        $u = $this->warga('Pagi Terus');
        $this->kuliah($u, array_fill_keys(range(1, 7), '07:00'));

        $hasil = $this->susun();

        $this->assertNull($this->hariDari($hasil['jadwal'], $u));
        $this->assertCount(1, $hasil['peringatan']);
        $this->assertStringContainsString('Pagi Terus', $hasil['peringatan'][0]);
    }

    public function test_lebih_dari_7_anggota_sisanya_masuk_peringatan(): void
    {
        foreach (range(1, 9) as $i) {
            $this->warga("W{$i}");
        }

        $hasil = $this->susun();

        $this->assertCount(7, array_filter($hasil['jadwal']));
        $this->assertCount(2, $hasil['peringatan']);
    }
}
