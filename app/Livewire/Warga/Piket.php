<?php

namespace App\Livewire\Warga;

use App\Enums\JenisTugas;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\TandaiSelesaiSendiri;
use App\Models\Tugas;
use App\Services\IzinPulang;
use App\Services\PelaksanaanPiket;
use App\Services\RingkasanWarga;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'piket'])]
#[Title('Piket saya')]
class Piket extends Component
{
    use BeriUmpanBalik, TandaiSelesaiSendiri;

    // Form izin pulang (R3).
    public string $izin_mulai = '';

    public string $izin_selesai = '';

    public string $izin_alasan = '';

    public function ajukanIzin(IzinPulang $izin, RingkasanWarga $ringkas): void
    {
        $this->validate([
            'izin_mulai' => ['required', 'date_format:Y-m-d'],
            'izin_selesai' => ['required', 'date_format:Y-m-d'],
            'izin_alasan' => ['nullable', 'string', 'max:500'],
        ], [
            'izin_mulai.required' => 'Isi tanggal berangkat.',
            'izin_selesai.required' => 'Isi tanggal kembali.',
            'izin_mulai.date_format' => 'Tanggal berangkat tidak valid.',
            'izin_selesai.date_format' => 'Tanggal kembali tidak valid.',
        ]);

        $tz = config('app.timezone');
        $ok = $this->coba(fn () => $izin->ajukan(
            auth()->user(),
            CarbonImmutable::createFromFormat('!Y-m-d', $this->izin_mulai, $tz),
            CarbonImmutable::createFromFormat('!Y-m-d', $this->izin_selesai, $tz),
            $this->izin_alasan,
        ), 'Izin pulang terkirim. Tunggu jawaban pengurus, ya.');

        if ($ok) {
            $this->reset('izin_mulai', 'izin_selesai', 'izin_alasan');
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
            'hariIni' => $sekarang->toDateString(),
        ]);
    }
}
