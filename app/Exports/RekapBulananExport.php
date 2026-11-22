<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * F10: file ekspor rekap bulanan (xlsx atau CSV) dari baris yang sama dengan tabel di halaman Rekap,
 * jadi angkanya pasti cocok. Baris terakhir berisi total.
 */
class RekapBulananExport implements FromArray, ShouldAutoSize, WithCustomCsvSettings, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public const KOLOM_ANGKA = ['terjadwal', 'selesai', 'bintang_baru', 'lunas', 'utang'];

    /** @param  Collection<int, array<string, mixed>>  $warga  baris dari Rekap (kunci user + KOLOM_ANGKA) */
    public function __construct(private CarbonImmutable $bulan, private Collection $warga) {}

    public function headings(): array
    {
        return ['Nama', 'Geng', 'Jumlah piket', 'Selesai', 'Bintang baru', 'Lunas', 'Utang'];
    }

    public function array(): array
    {
        $baris = $this->warga->map(fn (array $w) => [
            $w['user']->nama.($w['user']->aktif ? '' : ' (nonaktif)'),
            $w['user']->geng?->nama ?? '-',
            ...array_map(fn ($k) => (int) $w[$k], self::KOLOM_ANGKA),
        ])->all();

        $baris[] = ['Total', '', ...array_map(fn ($k) => (int) $this->warga->sum($k), self::KOLOM_ANGKA)];

        return $baris;
    }

    public function title(): string
    {
        return 'Rekap '.$this->bulan->translatedFormat('M Y');
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
            $this->warga->count() + 2 => ['font' => ['bold' => true]],
        ];
    }

    /** CSV: titik koma dan BOM UTF-8 supaya langsung rapi di Excel berbahasa Indonesia. */
    public function getCsvSettings(): array
    {
        return ['delimiter' => ';', 'use_bom' => true];
    }
}
