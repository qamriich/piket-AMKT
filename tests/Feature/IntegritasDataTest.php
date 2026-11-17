<?php

namespace Tests\Feature;

use App\Enums\JenisTugas;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan integritas data (PRD bagian 6), ditegakkan di level database.
 */
class IntegritasDataTest extends TestCase
{
    use RefreshDatabase;

    private MingguPiket $minggu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->minggu = MingguPiket::create([
            'tanggal_senin' => '2026-11-16',
            'geng_harian_id' => $this->geng(1)->id,
        ]);
    }

    private function penugasan(array $data): Penugasan
    {
        return Penugasan::create(array_merge([
            'minggu_piket_id' => $this->minggu->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'terjadwal',
        ], $data));
    }

    public function test_satu_tugas_kamis_tidak_bisa_punya_dua_petugas(): void
    {
        $wc1 = Tugas::create(['nama' => 'WC 1', 'jenis' => JenisTugas::KamisMinggu]);
        $base = ['tugas_id' => $wc1->id, 'jenis' => 'kamis', 'tanggal' => '2026-11-19', 'tenggat' => '2026-11-20 18:00:00'];

        $this->penugasan($base);

        $this->expectException(QueryException::class);
        $this->penugasan($base);
    }

    public function test_tugas_yang_sama_boleh_di_kamis_dan_minggu(): void
    {
        $wc1 = Tugas::create(['nama' => 'WC 1', 'jenis' => JenisTugas::KamisMinggu]);

        $this->penugasan(['tugas_id' => $wc1->id, 'jenis' => 'kamis', 'tanggal' => '2026-11-19', 'tenggat' => '2026-11-20 18:00:00']);
        $this->penugasan(['tugas_id' => $wc1->id, 'jenis' => 'minggu', 'tanggal' => '2026-11-22', 'tenggat' => '2026-11-23 18:00:00']);

        $this->assertSame(2, Penugasan::count());
    }

    public function test_satu_tanggal_harian_hanya_boleh_satu_penugasan(): void
    {
        $base = ['jenis' => 'harian', 'tanggal' => '2026-11-17', 'tenggat' => '2026-11-17 09:00:00'];
        $this->penugasan($base);

        $this->expectException(QueryException::class);
        $this->penugasan($base);
    }

    public function test_kerja_bakti_boleh_banyak_orang_di_tanggal_yang_sama(): void
    {
        $base = ['jenis' => 'kerja_bakti', 'tanggal' => '2026-11-22', 'tenggat' => '2026-11-23 18:00:00'];
        $this->penugasan($base);
        $this->penugasan($base);
        $this->penugasan($base);

        $this->assertSame(3, Penugasan::count());
    }

    public function test_relasi_bayar_bintang_ke_bintang(): void
    {
        $tugas = Tugas::create(['nama' => 'Pelataran', 'jenis' => JenisTugas::KamisMinggu]);
        $bintang = $this->penugasan(['tugas_id' => $tugas->id, 'jenis' => 'kamis', 'tanggal' => '2026-11-19', 'tenggat' => '2026-11-20 18:00:00', 'status' => 'bintang']);

        $minggu2 = MingguPiket::create(['tanggal_senin' => '2026-11-23', 'geng_harian_id' => $this->geng(2)->id]);
        $bayar = Penugasan::create([
            'minggu_piket_id' => $minggu2->id, 'user_id' => $bintang->user_id, 'tugas_id' => $tugas->id,
            'jenis' => 'kamis', 'tanggal' => '2026-11-26', 'tenggat' => '2026-11-27 18:00:00',
            'status' => 'bayar_bintang', 'membayar_penugasan_id' => $bintang->id,
        ]);

        $this->assertTrue($bayar->membayar->is($bintang));
        $this->assertTrue($bintang->pembayaran()->first()->is($bayar));
    }
}
