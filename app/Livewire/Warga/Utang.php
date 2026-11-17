<?php

namespace App\Livewire\Warga;

use App\Enums\StatusPengajuan;
use App\Services\RingkasanWarga;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'utang'])]
#[Title('Utang piket')]
class Utang extends Component
{
    public function render(RingkasanWarga $ringkas)
    {
        $user = auth()->user();
        $pengajuan = $ringkas->pengajuanSaya($user);

        return view('livewire.warga.utang', [
            'bintang' => $ringkas->bintangSaya($user),
            'menunggu' => $pengajuan->where('status', StatusPengajuan::Menunggu)->keyBy('penugasan_bintang_id'),
            'tanggalKosong' => $ringkas->tanggalKosong(),
        ]);
    }
}
