<?php

namespace App\Livewire\Warga;

use App\Models\Pengaturan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'kuliah'])]
#[Title('Jadwal kuliah')]
class Kuliah extends Component
{
    public function render()
    {
        return view('livewire.warga.kuliah', [
            'batas' => str_replace(':', '.', substr((string) Pengaturan::ambil('batas_jam_kuliah', '09:00'), 0, 5)),
        ]);
    }
}
