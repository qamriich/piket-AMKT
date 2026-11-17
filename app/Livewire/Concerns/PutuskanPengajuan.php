<?php

namespace App\Livewire\Concerns;

use App\Models\PengajuanGantiBintang;
use App\Services\GantiBintang;

/**
 * F8: tombol Terima/Tolak pengajuan ganti bintang (Pengawasan dan Pengajuan). Butuh trait BeriUmpanBalik.
 */
trait PutuskanPengajuan
{
    public function terimaPengajuan(int $id, ?string $catatan = null): void
    {
        $a = PengajuanGantiBintang::query()->with('user')->find($id);
        if (! $a) {
            $this->toast('Pengajuan tidak ditemukan. Muat ulang halaman.', 'gagal');

            return;
        }

        $this->coba(fn () => app(GantiBintang::class)->terima($a, auth()->user(), $catatan),
            "Diterima. {$a->user->namaDepan()} piket ganti bintang {$a->tanggal_diajukan->translatedFormat('l, j M')}.");
    }

    public function tolakPengajuan(int $id, ?string $catatan = null): void
    {
        $a = PengajuanGantiBintang::query()->with('user')->find($id);
        if (! $a) {
            $this->toast('Pengajuan tidak ditemukan. Muat ulang halaman.', 'gagal');

            return;
        }

        $this->coba(fn () => app(GantiBintang::class)->tolak($a, auth()->user(), $catatan),
            "Pengajuan {$a->user->namaDepan()} ditolak.");
    }
}
