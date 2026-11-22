<?php

namespace Tests\Feature;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPenugasan;
use App\Exports\RekapBulananExport;
use App\Livewire\Pengurus\Rekap;
use App\Models\LogAktivitas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\BuatPiket;
use Tests\TestCase;

/**
 * F10 + kriteria penerimaan: rekap bulanan bisa diekspor dan angkanya cocok dengan data penugasan.
 */
class EksporRekapTest extends TestCase
{
    use BuatPiket, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        $this->waktu('2026-11-25 10:00');
    }

    public function test_ekspor_excel_berisi_angka_yang_sama_dengan_tabel_rekap(): void
    {
        $m = $this->mingguTerbit();
        $gita = $this->warga('Gita', 1);
        $nivia = $this->warga('Nivia', 1);
        $this->piket($m, $gita, JenisPenugasan::Harian, '2026-11-16', StatusPenugasan::Selesai);
        $this->piket($m, $gita, JenisPenugasan::Harian, '2026-11-17', StatusPenugasan::Bintang);
        $this->piket($m, $nivia, JenisPenugasan::Harian, '2026-11-18', StatusPenugasan::Selesai);
        $this->masukSebagaiPengurus();

        $c = Livewire::test(Rekap::class, ['bulan' => '2026-11']);
        $warga = $c->viewData('warga');

        $c->call('ekspor')->assertFileDownloaded('rekap-piket-2026-11.xlsx');
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'rekap.ekspor')->exists());

        // Isi file xlsx dibaca ulang dan dibandingkan dengan baris tabel di halaman.
        $berkas = tempnam(sys_get_temp_dir(), 'rekap');
        file_put_contents($berkas, Excel::raw(new RekapBulananExport(CarbonImmutable::parse('2026-11-01'), $warga), ExcelFormat::XLSX));
        $sheet = IOFactory::createReader('Xlsx')->load($berkas)->getActiveSheet();
        @unlink($berkas);
        $isi = $sheet->toArray(null, true, false); // nilai mentah, bukan teks terformat

        $this->assertSame(['Nama', 'Geng', 'Jumlah piket', 'Selesai', 'Bintang baru', 'Lunas', 'Utang'], $isi[0]);
        $baris = collect($isi)->keyBy(0);
        $this->assertEquals([2, 1, 1, 0, 1], array_slice($baris['Gita'], 2));
        $this->assertEquals([1, 1, 0, 0, 0], array_slice($baris['Nivia'], 2));

        $total = end($isi);
        $this->assertSame('Total', $total[0]);
        foreach (RekapBulananExport::KOLOM_ANGKA as $i => $k) {
            $this->assertEquals($warga->sum($k), $total[$i + 2]);
        }

        // Sel angka tersimpan sebagai angka (bisa dijumlah di Excel), dan 0 tetap 0, bukan sel kosong.
        $this->assertSame('n', $sheet->getCell('C2')->getDataType());
        $this->assertSame(0, (int) $baris['Nivia'][4]);
        $this->assertNotNull($baris['Nivia'][4]);
    }

    public function test_ekspor_csv_memakai_titik_koma_dan_bom(): void
    {
        $m = $this->mingguTerbit();
        $this->piket($m, $this->warga('Ping', 1), JenisPenugasan::Harian, '2026-11-16', StatusPenugasan::Selesai);
        $this->masukSebagaiPengurus();

        $warga = Livewire::test(Rekap::class, ['bulan' => '2026-11'])->viewData('warga');
        $csv = Excel::raw(new RekapBulananExport(CarbonImmutable::parse('2026-11-01'), $warga), ExcelFormat::CSV);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Nama";"Geng";"Jumlah piket"', $csv);
        $this->assertStringContainsString('"Ping"', $csv);
    }

    public function test_nama_file_mengikuti_filter_jenis(): void
    {
        $this->masukSebagaiPengurus();

        Livewire::test(Rekap::class, ['bulan' => '2026-11', 'jenis' => 'kamis'])
            ->call('ekspor', 'xlsx')
            ->assertFileDownloaded('rekap-piket-2026-11-kamis.xlsx');
    }
}
