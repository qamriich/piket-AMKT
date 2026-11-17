<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use Carbon\CarbonImmutable;

/**
 * Waktu pengerjaan dan tenggat setiap jenis piket (PRD bagian 3 dan B4).
 *
 * - Harian: jam 04.00–09.00, tenggat hari itu jam 09.00.
 * - Kamis: tenggat Jumat jam 18.00.
 * - Minggu dan kerja bakti: tenggat Senin jam 18.00.
 */
class Tenggat
{
    public static function untuk(JenisPenugasan $jenis, CarbonImmutable $tanggal): CarbonImmutable
    {
        $hari = $tanggal->startOfDay();

        return match ($jenis) {
            JenisPenugasan::Harian => $hari->setTime(9, 0),
            JenisPenugasan::Kamis, JenisPenugasan::Minggu, JenisPenugasan::KerjaBakti => $hari->addDay()->setTime(18, 0),
        };
    }

    public static function mulai(JenisPenugasan $jenis, CarbonImmutable $tanggal): CarbonImmutable
    {
        return $jenis === JenisPenugasan::Harian
            ? $tanggal->startOfDay()->setTime(4, 0)
            : $tanggal->startOfDay();
    }

    /**
     * Seluruh waktu pengerjaan (R3a): harian 04.00–09.00 di hari itu, Kamis dari Kamis 00.00 sampai Jumat 18.00,
     * Minggu dan kerja bakti dari Minggu 00.00 sampai Senin 18.00. Piket hanya terkena izin kalau izinnya
     * menutupi seluruh rentang ini.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function jendela(JenisPenugasan $jenis, CarbonImmutable $tanggal): array
    {
        return [self::mulai($jenis, $tanggal), self::untuk($jenis, $tanggal)];
    }

    /** Contoh: "Jumat 18.00" atau "hari ini 09.00". */
    public static function label(CarbonImmutable $waktu, ?CarbonImmutable $sekarang = null): string
    {
        $sekarang ??= CarbonImmutable::now();
        $jam = $waktu->format('H.i');

        if ($waktu->isSameDay($sekarang)) {
            return "hari ini {$jam}";
        }
        if ($waktu->isSameDay($sekarang->addDay())) {
            return "besok, {$waktu->translatedFormat('l')} {$jam}";
        }

        return $waktu->translatedFormat('l, j M').' · '.$jam;
    }
}
