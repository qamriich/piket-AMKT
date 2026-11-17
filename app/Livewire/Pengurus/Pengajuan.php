<?php

namespace App\Livewire\Pengurus;

use App\Enums\StatusPengajuan;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\PengajuanGantiBintang;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Daftar pengajuan ganti bintang harian (F8). Tombol Terima/Tolak diaktifkan di Tahap 3.
 */
#[Layout('components.layouts.pengurus')]
#[Title('Pengajuan')]
class Pengajuan extends Component
{
    use KhususPengurus;

    #[Url(except: 'menunggu')]
    public string $tampil = 'menunggu';

    public function setTampil(string $tampil): void
    {
        $this->tampil = $tampil === 'riwayat' ? 'riwayat' : 'menunggu';
    }

    public function render()
    {
        $daftar = PengajuanGantiBintang::query()
            ->with(['user.geng', 'penugasanBintang', 'diputuskanOleh'])
            ->when($this->tampil === 'menunggu', fn ($q) => $q->where('status', StatusPengajuan::Menunggu->value)->oldest())
            ->when($this->tampil === 'riwayat', fn ($q) => $q->where('status', '!=', StatusPengajuan::Menunggu->value)->latest('diputuskan_pada'))
            ->limit(100)
            ->get();

        return view('livewire.pengurus.pengajuan', [
            'daftar' => $daftar,
            'jumlahMenunggu' => PengajuanGantiBintang::query()->where('status', StatusPengajuan::Menunggu->value)->count(),
        ]);
    }
}
