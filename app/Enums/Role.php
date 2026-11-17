<?php

namespace App\Enums;

enum Role: string
{
    case Warga = 'warga';
    case Ketua = 'ketua';
    case Sekretaris = 'sekretaris';
    case Bendahara = 'bendahara';
    case TataTertib = 'tata_tertib';

    public function label(): string
    {
        return match ($this) {
            self::Warga => 'Warga',
            self::Ketua => 'Ketua',
            self::Sekretaris => 'Sekretaris',
            self::Bendahara => 'Bendahara',
            self::TataTertib => 'Tata Tertib',
        };
    }

    public function isPengurus(): bool
    {
        return $this !== self::Warga;
    }

    /**
     * Urutan prioritas pemeriksa (B7). Hanya keterangan, tidak dipaksakan sistem.
     */
    public function prioritasPemeriksa(): ?int
    {
        return match ($this) {
            self::TataTertib => 1,
            self::Bendahara => 2,
            self::Sekretaris => 3,
            self::Ketua => 4,
            self::Warga => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
