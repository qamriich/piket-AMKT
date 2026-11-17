<?php

namespace App\Enums;

enum SesiTugas: string
{
    case Kamis = 'kamis';
    case Minggu = 'minggu';

    public function label(): string
    {
        return match ($this) {
            self::Kamis => 'Kamis',
            self::Minggu => 'Minggu',
        };
    }

    /** @return list<string> Jenis penugasan yang termasuk sesi ini. */
    public function jenisPenugasan(): array
    {
        return match ($this) {
            self::Kamis => [JenisPenugasan::Kamis->value],
            self::Minggu => [JenisPenugasan::Minggu->value, JenisPenugasan::KerjaBakti->value],
        };
    }
}
