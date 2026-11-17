<?php

namespace App\Enums;

enum ModeMinggu: string
{
    case TugasKecil = 'tugas_kecil';
    case KerjaBakti = 'kerja_bakti';

    public function label(): string
    {
        return match ($this) {
            self::TugasKecil => 'Tugas kecil',
            self::KerjaBakti => 'Kerja bakti',
        };
    }
}
