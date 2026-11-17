<?php

namespace App\Livewire\Warga;

use App\Enums\JenisTugas;
use App\Models\Tugas;
use App\Services\RingkasanWarga;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'piket'])]
#[Title('Piket saya')]
class Piket extends Component
{
    public function render(RingkasanWarga $ringkas)
    {
        $user = auth()->user();

        return view('livewire.warga.piket', [
            'user' => $user,
            'sekarang' => $ringkas->sekarang(),
            'piket' => $ringkas->piketAktif($user),
            'tugasHarian' => Tugas::query()->jenis(JenisTugas::Harian)->urut()->pluck('nama'),
            'pengajuan' => $ringkas->pengajuanSaya($user)->take(5),
        ]);
    }
}
