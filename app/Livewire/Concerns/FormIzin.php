<?php

namespace App\Livewire\Concerns;

use Carbon\CarbonImmutable;

/**
 * Isian mulai dan selesai izin dengan tanggal + jam (R3a), dipakai di Piket saya dan Pengajuan.
 * Nilainya berformat input datetime-local: "2026-11-19T08:00", dalam WITA.
 */
trait FormIzin
{
    public const FORMAT_IZIN = 'Y-m-d\TH:i';

    public string $izin_mulai = '';

    public string $izin_selesai = '';

    public string $izin_alasan = '';

    /** Tombol cepat "Hari ini sampai besok": hari ini 00.00 sampai besok 23.59. */
    public function izinHariIniBesok(): void
    {
        $hariIni = CarbonImmutable::today();
        $this->izin_mulai = $hariIni->format(self::FORMAT_IZIN);
        $this->izin_selesai = $hariIni->addDay()->setTime(23, 59)->format(self::FORMAT_IZIN);
        $this->resetValidation(['izin_mulai', 'izin_selesai']);
    }

    /** Tombol cepat "Sehari penuh": 00.00–23.59 di tanggal mulai yang sudah dipilih (atau hari ini). */
    public function izinSehariPenuh(): void
    {
        $hari = $this->waktuIzin($this->izin_mulai)?->startOfDay() ?? CarbonImmutable::today();
        $this->izin_mulai = $hari->format(self::FORMAT_IZIN);
        $this->izin_selesai = $hari->setTime(23, 59)->format(self::FORMAT_IZIN);
        $this->resetValidation(['izin_mulai', 'izin_selesai']);
    }

    protected function aturanFormIzin(): array
    {
        return [
            'izin_mulai' => ['required', 'date_format:'.self::FORMAT_IZIN],
            'izin_selesai' => ['required', 'date_format:'.self::FORMAT_IZIN],
            'izin_alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function pesanFormIzin(): array
    {
        return [
            'izin_mulai.required' => 'Isi kapan mulai izin.',
            'izin_selesai.required' => 'Isi kapan izin selesai.',
            'izin_mulai.date_format' => 'Tanggal dan jam mulai tidak valid.',
            'izin_selesai.date_format' => 'Tanggal dan jam selesai tidak valid.',
        ];
    }

    protected function waktuIzin(string $nilai): ?CarbonImmutable
    {
        try {
            $t = CarbonImmutable::createFromFormat('!'.self::FORMAT_IZIN, $nilai, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $t && $t->format(self::FORMAT_IZIN) === $nilai ? $t : null;
    }

    protected function resetFormIzin(): void
    {
        $this->reset('izin_mulai', 'izin_selesai', 'izin_alasan');
    }
}
