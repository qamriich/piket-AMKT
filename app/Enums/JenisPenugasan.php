<?php

namespace App\Enums;

enum JenisPenugasan: string
{
    case Harian = 'harian';
    case Kamis = 'kamis';
    case Minggu = 'minggu';
    case KerjaBakti = 'kerja_bakti';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::Kamis => 'Kamis',
            self::Minggu => 'Minggu',
            self::KerjaBakti => 'Kerja bakti',
        };
    }

    /** Kolom status terbit di tabel minggu_piket untuk jenis ini. */
    public function kolomStatus(): string
    {
        return match ($this) {
            self::Harian => 'status_harian',
            self::Kamis => 'status_kamis',
            self::Minggu, self::KerjaBakti => 'status_minggu',
        };
    }
}
