<?php

namespace App\Enums;

/**
 * Status piket (aturan B6, revisi R4 di PRD bagian 15).
 */
enum StatusPenugasan: string
{
    case Terjadwal = 'terjadwal';
    case BayarBintang = 'bayar_bintang';
    case Selesai = 'selesai';
    case Bintang = 'bintang';
    case Lunas = 'lunas';
    case Izin = 'izin';

    /** Label untuk pengguna. "terjadwal" ditampilkan sebagai "Belum dikerjakan" (R4). */
    public function label(): string
    {
        return match ($this) {
            self::Terjadwal => 'Belum dikerjakan',
            self::BayarBintang => 'Bayar Bintang',
            self::Selesai => 'Selesai',
            self::Bintang => 'Bintang',
            self::Lunas => 'Lunas',
            self::Izin => 'Izin',
        };
    }

    /**
     * Kelas badge (bagian 7a + R4): Belum dikerjakan biru, Bayar Bintang oranye, Selesai hijau,
     * Bintang merah, Lunas teal, Izin abu-abu. Warnanya ada di resources/css/app.css.
     */
    public function badgeClass(): string
    {
        return 'badge badge-'.$this->value;
    }

    /** Piket yang masih harus dikerjakan. Piket berstatus Izin tidak perlu dikerjakan dan tidak menjadi bintang. */
    public function belumSelesai(): bool
    {
        return $this === self::Terjadwal || $this === self::BayarBintang;
    }
}
