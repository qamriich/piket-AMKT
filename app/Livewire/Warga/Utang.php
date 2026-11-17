<?php

namespace App\Livewire\Warga;

use App\Enums\StatusPenugasan;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Models\Penugasan;
use App\Services\GantiBintang;
use App\Services\RingkasanWarga;
use App\Services\RotasiGeng;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'utang'])]
#[Title('Utang piket')]
class Utang extends Component
{
    use BeriUmpanBalik;

    /** F8: ajukan ganti satu bintang harian di tanggal kosong. */
    public function ajukan(int $bintangId, string $tanggal, GantiBintang $ganti, RingkasanWarga $ringkas): void
    {
        $bintang = Penugasan::query()->where('user_id', auth()->id())->find($bintangId);
        try {
            $t = CarbonImmutable::createFromFormat('!Y-m-d', $tanggal, config('app.timezone')) ?: null;
        } catch (\Throwable) {
            $t = null;
        }

        if (! $bintang || ! $t || $t->toDateString() !== $tanggal) {
            $this->toast('Pilih hari kosong dulu, ya.', 'gagal');

            return;
        }

        if ($this->coba(fn () => $ganti->ajukan(auth()->user(), $bintang, $t), 'Pengajuan terkirim. Tunggu jawaban pengurus, ya.')) {
            $this->dispatch('ganti-terkirim', id: $bintang->id);
        }
        $ringkas->lupakan();
    }

    public function render(RingkasanWarga $ringkas)
    {
        $user = auth()->user();
        $pengajuan = $ringkas->pengajuanSaya($user);

        $bintang = $ringkas->bintangSaya($user);

        return view('livewire.warga.utang', [
            // R6: bintang yang belum lunas tampil sebagai kartu di atas, riwayat yang sudah lunas di bawah.
            'belumLunas' => $bintang->where('status', StatusPenugasan::Bintang)->values(),
            'lunas' => $bintang->where('status', StatusPenugasan::Lunas)->values(),
            // Pengajuan terakhir untuk setiap bintang (Menunggu, Diterima, atau Ditolak).
            'pengajuanTerakhir' => $pengajuan->groupBy('penugasan_bintang_id')->map->first(),
            'kosongPerMinggu' => $ringkas->tanggalKosong()->groupBy(fn (CarbonImmutable $t) => RotasiGeng::seninDari($t)->toDateString()),
        ]);
    }
}
