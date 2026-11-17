<?php

namespace App\Enums;

enum JenisTugas: string
{
    case Harian = 'harian';
    case KamisMinggu = 'kamis_minggu';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::KamisMinggu => 'Kamis & Minggu',
        };
    }
}
