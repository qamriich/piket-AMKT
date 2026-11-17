<?php

namespace Tests\Unit;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\ModeMinggu;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\TugasAktif;
use App\Models\User;
use App\Services\JadwalMinggu;
use App\Services\PenugasanOtomatis;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan B3 dan B3a, termasuk contoh di kriteria penerimaan (PRD bagian 10).
 */
class PenugasanOtomatisTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Tugas> */
    private array $tugas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();

        foreach (['WC 1', 'WC 2', 'WC 3', 'WC 4', 'Pelataran', 'Aula depan'] as $i => $nama) {
            $this->tugas[$nama] = Tugas::create(['nama' => $nama, 'jenis' => JenisTugas::KamisMinggu, 'urutan' => $i + 1]);
        }
    }

    private function layanan(): PenugasanOtomatis
    {
        // Pemecah seri acak dibuat stabil (urut id) supaya test deterministik.
        return app(PenugasanOtomatis::class)->denganPengacak(fn (array $ids) => $ids);
    }

    /** Minggu dengan geng harian tetap Geng 1, supaya pool Kamis = Geng 2 & 3. */
    private function minggu(string $senin, array $tugasAktif, string $sesi = 'kamis', ModeMinggu $mode = ModeMinggu::TugasKecil): MingguPiket
    {
        $m = MingguPiket::create(['tanggal_senin' => $senin, 'geng_harian_id' => $this->geng(1)->id, 'mode_minggu' => $mode]);
        foreach ($tugasAktif as $nama) {
            TugasAktif::create(['minggu_piket_id' => $m->id, 'tugas_id' => $this->tugas[$nama]->id, 'sesi' => $sesi]);
        }

        return $m;
    }

    private function warga(string $nama, int $geng = 2): User
    {
        return User::factory()->create(['nama' => $nama, 'geng_id' => $this->geng($geng)->id]);
    }

    private function riwayat(User $u, string $tugas, string $tanggal, MingguPiket $m, StatusPenugasan $status = StatusPenugasan::Selesai): Penugasan
    {
        return Penugasan::create([
            'minggu_piket_id' => $m->id, 'user_id' => $u->id, 'tugas_id' => $this->tugas[$tugas]->id,
            'jenis' => 'kamis', 'tanggal' => $tanggal, 'tenggat' => CarbonImmutable::parse($tanggal)->addDay()->setTime(18, 0),
            'status' => $status,
        ]);
    }

    /** @return array<string, string> nama tugas => nama petugas */
    private function peta(MingguPiket $m, string $jenis = 'kamis'): array
    {
        return $m->penugasan()->where('jenis', $jenis)->with(['tugas', 'user'])->get()
            ->mapWithKeys(fn (Penugasan $p) => [$p->tugas->nama => $p->user->nama])->all();
    }

    public function test_contoh_pengurus_wc_1_sampai_4_dibagi_tanpa_nama_ganda(): void
    {
        [$nivia, $dilla, $gita, $dewi] = array_map(fn ($n) => $this->warga($n), ['Nivia', 'Dilla', 'Gita', 'Dewi']);

        // Riwayat 3 minggu terakhir: setiap orang sudah bergiliran sehingga giliran berikutnya jelas.
        $m1 = $this->minggu('2026-10-26', []);
        $m2 = $this->minggu('2026-11-02', []);
        $m3 = $this->minggu('2026-11-09', []);
        foreach ([[$m1, '2026-10-29', [$dilla, $gita, $dewi, $nivia]], [$m2, '2026-11-05', [$gita, $dewi, $nivia, $dilla]], [$m3, '2026-11-12', [$dewi, $nivia, $dilla, $gita]]] as [$m, $tgl, $orang]) {
            foreach (['WC 1', 'WC 2', 'WC 3', 'WC 4'] as $i => $t) {
                $this->riwayat($orang[$i], $t, $tgl, $m);
            }
        }

        $minggu = $this->minggu('2026-11-16', ['WC 1', 'WC 2', 'WC 3', 'WC 4']);
        $this->layanan()->bagikan($minggu, SesiTugas::Kamis);

        $this->assertSame(['WC 1' => 'Nivia', 'WC 2' => 'Dilla', 'WC 3' => 'Gita', 'WC 4' => 'Dewi'], $this->peta($minggu));
    }

    public function test_tidak_ada_nama_ganda_dan_tidak_ada_tugas_ganda_tanpa_riwayat(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $i => $n) {
            $this->warga($n, $i % 2 ? 2 : 3);
        }
        $minggu = $this->minggu('2026-11-16', ['WC 1', 'WC 2', 'WC 3', 'WC 4']);

        app(PenugasanOtomatis::class)->bagikan($minggu, SesiTugas::Kamis);

        $baris = $minggu->penugasan()->get();
        $this->assertCount(4, $baris);
        $this->assertSame(4, $baris->pluck('user_id')->unique()->count());
        $this->assertSame(4, $baris->pluck('tugas_id')->unique()->count());
    }

    public function test_pool_kamis_hanya_dua_geng_non_harian_dan_warga_aktif(): void
    {
        $harian = $this->warga('Harian', 1);
        $nonaktif = User::factory()->nonaktif()->create(['geng_id' => $this->geng(2)->id]);
        $a = $this->warga('A', 2);
        $b = $this->warga('B', 3);

        $minggu = $this->minggu('2026-11-16', ['WC 1', 'WC 2', 'WC 3']);
        $hasil = $this->layanan()->bagikan($minggu, SesiTugas::Kamis);

        $ids = $minggu->penugasan()->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids);
        $this->assertNotContains($harian->id, $ids);
        $this->assertNotContains($nonaktif->id, $ids);
        $this->assertSame(['WC 3'], $hasil['tanpa_petugas']->pluck('nama')->all());
    }

    public function test_kasus_bintang_gita_dibayar_di_minggu_berikutnya(): void
    {
        [$nivia, $dilla, $gita, $dewi] = array_map(fn ($n) => $this->warga($n), ['Nivia', 'Dilla', 'Gita', 'Dewi']);

        // Minggu ke-1: Gita kena bintang pelataran. Nivia biasanya dapat pelataran.
        $m1 = $this->minggu('2026-11-16', ['WC 1', 'WC 2', 'WC 3', 'Pelataran']);
        $bintang = $this->riwayat($gita, 'Pelataran', '2026-11-19', $m1, StatusPenugasan::Bintang);
        $this->riwayat($nivia, 'WC 1', '2026-11-19', $m1);
        $this->riwayat($dilla, 'WC 2', '2026-11-19', $m1);
        $this->riwayat($dewi, 'WC 3', '2026-11-19', $m1);

        $m2 = $this->minggu('2026-11-23', ['WC 1', 'WC 2', 'WC 3', 'Pelataran']);
        $hasil = $this->layanan()->bagikan($m2, SesiTugas::Kamis);

        $bayar = $m2->penugasan()->where('tugas_id', $this->tugas['Pelataran']->id)->firstOrFail();
        $this->assertSame($gita->id, $bayar->user_id);
        $this->assertSame(StatusPenugasan::BayarBintang, $bayar->status);
        $this->assertSame($bintang->id, $bayar->membayar_penugasan_id);

        // Gita tetap mendapat 1 tugas reguler, jadi total 2 tugas. Tidak ada tugas dengan dua petugas.
        $gitaReguler = $m2->penugasan()->where('user_id', $gita->id)->where('status', 'terjadwal')->count();
        $this->assertSame(1, $gitaReguler);
        $this->assertSame(4, $m2->penugasan()->count());
        $this->assertSame(4, $m2->penugasan()->distinct()->count('tugas_id'));

        // Nivia tergeser dan mendapat tugas lain.
        $this->assertSame(1, $m2->penugasan()->where('user_id', $nivia->id)->count());
        $this->assertSame([], $hasil['ditunda']);
    }

    public function test_bintang_ditunda_kalau_tugasnya_nonaktif_lalu_dibayar_minggu_ke_3(): void
    {
        $gita = $this->warga('Gita');
        $this->warga('Nivia');

        $m1 = $this->minggu('2026-11-16', ['Pelataran']);
        $bintang = $this->riwayat($gita, 'Pelataran', '2026-11-19', $m1, StatusPenugasan::Bintang);

        // Minggu ke-2: pelataran dimatikan.
        $m2 = $this->minggu('2026-11-23', ['WC 1', 'WC 2']);
        $hasil2 = $this->layanan()->bagikan($m2, SesiTugas::Kamis);
        $this->assertFalse($m2->penugasan()->where('status', 'bayar_bintang')->exists());
        $this->assertCount(1, $hasil2['ditunda']);
        $this->assertTrue($hasil2['ditunda'][0]['bintang']->is($bintang));
        $m2->update(['status_kamis' => 'terbit']);

        // Minggu ke-3: pelataran aktif lagi, bintang Gita dibayar.
        $m3 = $this->minggu('2026-11-30', ['Pelataran', 'WC 1']);
        $this->layanan()->bagikan($m3, SesiTugas::Kamis);
        $bayar = $m3->penugasan()->where('status', 'bayar_bintang')->firstOrFail();
        $this->assertSame($gita->id, $bayar->user_id);
        $this->assertSame($this->tugas['Pelataran']->id, $bayar->tugas_id);
    }

    public function test_bintang_ditunda_kalau_pemiliknya_sedang_di_geng_harian(): void
    {
        $gita = $this->warga('Gita', 2);
        $this->warga('Lain', 3);
        $m1 = $this->minggu('2026-11-16', ['Pelataran']);
        $this->riwayat($gita, 'Pelataran', '2026-11-19', $m1, StatusPenugasan::Bintang);

        $m2 = MingguPiket::create(['tanggal_senin' => '2026-11-23', 'geng_harian_id' => $this->geng(2)->id]);
        TugasAktif::create(['minggu_piket_id' => $m2->id, 'tugas_id' => $this->tugas['Pelataran']->id, 'sesi' => 'kamis']);

        $hasil = $this->layanan()->bagikan($m2, SesiTugas::Kamis);

        $this->assertCount(1, $hasil['ditunda']);
        $this->assertNotSame($gita->id, $m2->penugasan()->value('user_id'));
    }

    public function test_bintang_yang_sedang_dibayar_di_minggu_lain_tidak_dijadwalkan_dua_kali(): void
    {
        $gita = $this->warga('Gita');
        $this->warga('Nivia');
        $m1 = $this->minggu('2026-11-16', ['Pelataran']);
        $this->riwayat($gita, 'Pelataran', '2026-11-19', $m1, StatusPenugasan::Bintang);

        $m2 = $this->minggu('2026-11-23', ['Pelataran']);
        $this->layanan()->bagikan($m2, SesiTugas::Kamis);
        $m2->update(['status_kamis' => 'terbit']);

        $m3 = $this->minggu('2026-11-30', ['Pelataran']);
        $this->layanan()->bagikan($m3, SesiTugas::Kamis);

        $this->assertFalse($m3->penugasan()->where('status', 'bayar_bintang')->exists());
    }

    public function test_bintang_lunas_tidak_dijadwalkan_lagi(): void
    {
        $gita = $this->warga('Gita');
        $m1 = $this->minggu('2026-11-16', ['Pelataran']);
        $bintang = $this->riwayat($gita, 'Pelataran', '2026-11-19', $m1, StatusPenugasan::Bintang);
        $m2 = $this->minggu('2026-11-23', []);
        Penugasan::create([
            'minggu_piket_id' => $m2->id, 'user_id' => $gita->id, 'tugas_id' => $this->tugas['Pelataran']->id, 'jenis' => 'kamis',
            'tanggal' => '2026-11-26', 'tenggat' => '2026-11-27 18:00:00', 'status' => 'selesai', 'membayar_penugasan_id' => $bintang->id,
        ]);

        $m3 = $this->minggu('2026-11-30', ['Pelataran']);
        $hasil = $this->layanan()->bagikan($m3, SesiTugas::Kamis);

        $this->assertFalse($m3->penugasan()->where('status', 'bayar_bintang')->exists());
        $this->assertSame([], $hasil['ditunda']);
    }

    public function test_orang_lebih_banyak_dari_tugas_yang_bebas_diprioritaskan_minggu_berikutnya(): void
    {
        $orang = array_map(fn ($n) => $this->warga($n), ['A', 'B', 'C', 'D']);

        $m1 = $this->minggu('2026-11-16', ['WC 1', 'WC 2']);
        $hasil1 = $this->layanan()->bagikan($m1, SesiTugas::Kamis);
        $bebas1 = $hasil1['bebas']->pluck('id')->all();
        $this->assertCount(2, $bebas1);
        $m1->update(['status_kamis' => 'terbit']);

        $m2 = $this->minggu('2026-11-23', ['WC 1', 'WC 2']);
        $this->layanan()->bagikan($m2, SesiTugas::Kamis);

        $this->assertEqualsCanonicalizing($bebas1, $m2->penugasan()->pluck('user_id')->all());
    }

    public function test_mode_kerja_bakti_semua_warga_aktif_dapat_satu_entri(): void
    {
        $aktif = collect([$this->warga('A', 1), $this->warga('B', 2), $this->warga('C', 3)]);
        User::factory()->nonaktif()->create(['geng_id' => $this->geng(1)->id]);

        $m = $this->minggu('2026-11-16', ['WC 1'], 'minggu', ModeMinggu::KerjaBakti);
        app(PenugasanOtomatis::class)->bagikan($m, SesiTugas::Minggu);

        $baris = $m->penugasan()->get();
        $this->assertCount(3, $baris);
        $this->assertTrue($baris->every(fn ($p) => $p->jenis === JenisPenugasan::KerjaBakti && $p->tugas_id === null));
        $this->assertEqualsCanonicalizing($aktif->pluck('id')->all(), $baris->pluck('user_id')->all());
        $this->assertSame('2026-11-23 18:00:00', $baris->first()->tenggat->format('Y-m-d H:i:s'));
    }

    public function test_bintang_kerja_bakti_dibayar_dengan_tugas_kecil_tambahan_minggu_berikutnya(): void
    {
        $gita = $this->warga('Gita', 1);
        $this->warga('B', 2);
        $this->warga('C', 3);

        $m1 = $this->minggu('2026-11-16', [], 'minggu', ModeMinggu::KerjaBakti);
        $bintang = Penugasan::create([
            'minggu_piket_id' => $m1->id, 'user_id' => $gita->id, 'jenis' => 'kerja_bakti',
            'tanggal' => '2026-11-22', 'tenggat' => '2026-11-23 18:00:00', 'status' => 'bintang',
        ]);

        // Minggu berikutnya kerja bakti lagi: bintang ditunda.
        $m2 = $this->minggu('2026-11-23', ['WC 1', 'WC 2', 'WC 3'], 'minggu', ModeMinggu::KerjaBakti);
        $hasil2 = $this->layanan()->bagikan($m2, SesiTugas::Minggu);
        $this->assertCount(1, $hasil2['ditunda']);
        $m2->update(['status_minggu' => 'terbit']);

        // Minggu berikutnya tugas kecil: Gita dapat 1 tugas Bayar Bintang + 1 tugas reguler.
        $m3 = $this->minggu('2026-11-30', ['WC 1', 'WC 2', 'WC 3', 'WC 4'], 'minggu');
        $this->layanan()->bagikan($m3, SesiTugas::Minggu);

        $milikGita = $m3->penugasan()->where('user_id', $gita->id)->get();
        $this->assertCount(2, $milikGita);
        $bayar = $milikGita->firstWhere('status', StatusPenugasan::BayarBintang);
        $this->assertNotNull($bayar);
        $this->assertSame($bintang->id, $bayar->membayar_penugasan_id);
        $this->assertSame(JenisPenugasan::Minggu, $bayar->jenis);
        $this->assertSame(4, $m3->penugasan()->distinct()->count('tugas_id'));
    }

    public function test_tenggat_kamis_jumat_18_dan_minggu_senin_18(): void
    {
        $this->warga('A', 2);
        $m = $this->minggu('2026-11-16', ['WC 1']);
        TugasAktif::create(['minggu_piket_id' => $m->id, 'tugas_id' => $this->tugas['WC 1']->id, 'sesi' => 'minggu']);

        $this->layanan()->bagikan($m, SesiTugas::Kamis);
        $this->layanan()->bagikan($m, SesiTugas::Minggu);

        $kamis = $m->penugasan()->where('jenis', 'kamis')->firstOrFail();
        $minggu = $m->penugasan()->where('jenis', 'minggu')->firstOrFail();
        $this->assertSame('2026-11-19', $kamis->tanggal->toDateString());
        $this->assertSame('2026-11-20 18:00', $kamis->tenggat->format('Y-m-d H:i'));
        $this->assertSame('2026-11-22', $minggu->tanggal->toDateString());
        $this->assertSame('2026-11-23 18:00', $minggu->tenggat->format('Y-m-d H:i'));
    }

    public function test_jadwal_yang_sudah_terbit_tidak_bisa_dibagi_ulang(): void
    {
        $m = $this->minggu('2026-11-16', ['WC 1']);
        $m->update(['status_kamis' => 'terbit']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PenugasanOtomatis::class)->bagikan($m, SesiTugas::Kamis);
    }

    public function test_toggle_mengikuti_minggu_sebelumnya(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00', 'Asia/Makassar'));
        $layanan = app(JadwalMinggu::class);

        $m1 = $layanan->siapkan(now());
        $this->assertCount(6, $layanan->tugasAktif($m1, SesiTugas::Kamis)); // semua aktif_default

        $layanan->ubahTugasAktif($m1, SesiTugas::Kamis, $this->tugas['WC 4'], false);
        $layanan->ubahModeMinggu($m1, ModeMinggu::KerjaBakti);

        $m2 = $layanan->siapkan(now()->addWeek());
        $this->assertCount(5, $layanan->tugasAktif($m2, SesiTugas::Kamis));
        $this->assertFalse($layanan->tugasAktif($m2, SesiTugas::Kamis)->contains('nama', 'WC 4'));
        $this->assertCount(6, $layanan->tugasAktif($m2, SesiTugas::Minggu));
        $this->assertSame(ModeMinggu::KerjaBakti, $m2->mode_minggu);
        $this->assertSame($this->geng(2)->id, $m2->geng_harian_id);
    }

    public function test_tukar_petugas_tidak_boleh_untuk_bayar_bintang(): void
    {
        $gita = $this->warga('Gita');
        $this->warga('Nivia');
        $m1 = $this->minggu('2026-11-16', ['Pelataran']);
        $this->riwayat($gita, 'Pelataran', '2026-11-19', $m1, StatusPenugasan::Bintang);

        $m2 = $this->minggu('2026-11-23', ['Pelataran', 'WC 1', 'WC 2']);
        $this->layanan()->bagikan($m2, SesiTugas::Kamis);
        $bayar = $m2->penugasan()->where('status', 'bayar_bintang')->firstOrFail();
        [$r1, $r2] = $m2->penugasan()->where('status', 'terjadwal')->get()->all();

        [$u1, $u2] = [$r1->user_id, $r2->user_id];
        app(JadwalMinggu::class)->tukar($r1, $r2);
        $this->assertSame($u2, $r1->fresh()->user_id);
        $this->assertSame($u1, $r2->fresh()->user_id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(JadwalMinggu::class)->tukar($bayar, $r1);
    }
}
