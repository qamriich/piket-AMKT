<?php

namespace App\Enums;

enum StatusPengajuan: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return 'badge badge-pengajuan-'.$this->value;
    }
}
