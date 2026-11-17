<?php

namespace App\Livewire\Warga;

use App\Enums\StatusPengajuan;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Models\Penugasan;
use App\Services\GantiBintang;
use App\Services\RingkasanWarga;
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

        $this->coba(fn () => $ganti->ajukan(auth()->user(), $bintang, $t), 'Pengajuan terkirim. Tunggu jawaban pengurus, ya.');
        $ringkas->lupakan();
    }

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
