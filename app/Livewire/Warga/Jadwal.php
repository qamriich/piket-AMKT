<?php

namespace App\Livewire\Warga;

use App\Services\RingkasanWarga;
use App\Services\RotasiGeng;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'jadwal'])]
#[Title('Jadwal minggu ini')]
class Jadwal extends Component
{
    public function render(RingkasanWarga $ringkas)
    {
        $sekarang = $ringkas->sekarang();
        $senin = RotasiGeng::seninDari($sekarang);

        // Tab awal mengikuti hari: Kamis–Jumat ke tab Kamis, Sabtu–Minggu ke tab Minggu.
        $tabAwal = match (true) {
            in_array($sekarang->dayOfWeekIso, [4, 5], true) => 'kamis',
            in_array($sekarang->dayOfWeekIso, [6, 7], true) => 'minggu',
            default => 'harian',
        };

        return view('livewire.warga.jadwal', [
            'user' => auth()->user(),
            'sekarang' => $sekarang,
            'senin' => $senin,
            'jadwal' => $ringkas->jadwalMingguIni(),
            'geng' => $ringkas->gengHarian(),
            'gengKamis' => $ringkas->gengKamis(),
            'tabAwal' => $tabAwal,
        ]);
    }
}
