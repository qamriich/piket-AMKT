<?php

namespace App\Livewire\Warga;

use App\Enums\JenisTugas;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\FormIzin;
use App\Livewire\Concerns\TandaiSelesaiSendiri;
use App\Models\Tugas;
use App\Services\LayananIzin;
use App\Services\PelaksanaanPiket;
use App\Services\RingkasanWarga;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'piket'])]
#[Title('Piket saya')]
class Piket extends Component
{
    use BeriUmpanBalik, FormIzin, TandaiSelesaiSendiri;

    /** R3/R3a: warga mengajukan izin dengan tanggal + jam. */
    public function ajukanIzin(LayananIzin $izin, RingkasanWarga $ringkas): void
    {
        $this->validate($this->aturanFormIzin(), $this->pesanFormIzin());

        $ok = $this->coba(fn () => $izin->ajukan(
            auth()->user(),
            $this->waktuIzin($this->izin_mulai),
            $this->waktuIzin($this->izin_selesai),
            $this->izin_alasan,
        ), 'Izin terkirim. Tunggu jawaban pengurus, ya.');

        if ($ok) {
            $this->resetFormIzin();
            $this->dispatch('izin-terkirim');
            $ringkas->lupakan();
        }
    }

    public function render(RingkasanWarga $ringkas, PelaksanaanPiket $pelaksanaan)
    {
        $user = auth()->user();
        $sekarang = $ringkas->sekarang();
        $piket = $ringkas->piketAktif($user);

        return view('livewire.warga.piket', [
            'user' => $user,
            'sekarang' => $sekarang,
            'piket' => $piket,
            'bisaSendiri' => $piket->filter(fn ($p) => $pelaksanaan->bisaDitandaiSendiri($p, $user, $sekarang))->pluck('id')->all(),
            'tugasHarian' => Tugas::query()->jenis(JenisTugas::Harian)->urut()->pluck('nama'),
            'pengajuan' => $ringkas->pengajuanSaya($user)->take(5),
            'izin' => $ringkas->izinSaya($user),
            'hariIni' => $sekarang->startOfDay()->format(self::FORMAT_IZIN),
        ]);
    }
}
