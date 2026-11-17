<?php

namespace App\Enums;

/**
 * Status piket (aturan B6).
 */
enum StatusPenugasan: string
{
    case Terjadwal = 'terjadwal';
    case BayarBintang = 'bayar_bintang';
    case Selesai = 'selesai';
    case Bintang = 'bintang';
    case Lunas = 'lunas';

    public function label(): string
    {
        return match ($this) {
            self::Terjadwal => 'Terjadwal',
            self::BayarBintang => 'Bayar Bintang',
            self::Selesai => 'Selesai',
            self::Bintang => 'Bintang',
            self::Lunas => 'Lunas',
        };
    }

    /**
     * Kelas badge (bagian 7a): Terjadwal biru, Bayar Bintang oranye, Selesai hijau,
     * Bintang merah, Lunas teal. Warnanya ada di resources/css/app.css.
     */
    public function badgeClass(): string
    {
        return 'badge badge-'.$this->value;
    }

    /** Piket yang masih harus dikerjakan. */
    public function belumSelesai(): bool
    {
        return $this === self::Terjadwal || $this === self::BayarBintang;
    }
}
