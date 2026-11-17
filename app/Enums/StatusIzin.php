<?php

namespace App\Enums;

/** Status izin (PRD bagian 15 R3). */
enum StatusIzin: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Memakai warna badge pengajuan: menunggu kuning, disetujui hijau, ditolak merah. */
    public function badgeClass(): string
    {
        return 'badge badge-pengajuan-'.match ($this) {
            self::Menunggu => 'menunggu',
            self::Disetujui => 'diterima',
            self::Ditolak => 'ditolak',
        };
    }
}
