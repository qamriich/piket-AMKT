<?php

namespace App\Livewire\Concerns;

use App\Models\Penugasan;
use App\Services\PelaksanaanPiket;
use App\Services\RingkasanWarga;

/**
 * F6: warga menandai piketnya sendiri selesai tanpa reload (Beranda dan Piket saya).
 * Butuh trait BeriUmpanBalik.
 */
trait TandaiSelesaiSendiri
{
    /** Id piket yang baru saja ditandai, supaya kartunya memutar animasi centang. */
    public ?int $baruSelesai = null;

    public function selesai(int $id, PelaksanaanPiket $pelaksanaan, RingkasanWarga $ringkas): void
    {
        $p = Penugasan::query()->where('user_id', auth()->id())->find($id);
        if (! $p) {
            $this->toast('Piket tidak ditemukan. Muat ulang halaman.', 'gagal');

            return;
        }

        if ($this->coba(fn () => $pelaksanaan->tandaiSelesai($p, auth()->user()), 'Mantap, piketmu tercatat selesai!')) {
            $this->baruSelesai = $p->id;
        }
        $ringkas->lupakan();
    }
}
