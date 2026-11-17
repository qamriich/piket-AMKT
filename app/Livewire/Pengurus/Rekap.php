<?php

namespace App\Livewire\Pengurus;

use App\Enums\StatusPenugasan;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\Penugasan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * F10: rekap bulanan per warga. Ekspor Excel/CSV menyusul di Tahap 4.
 */
#[Layout('components.layouts.pengurus')]
#[Title('Rekap bulanan')]
class Rekap extends Component
{
    use KhususPengurus;

    #[Url]
    public string $bulan = '';

    #[Url(except: '')]
    public string $jenis = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = CarbonImmutable::now()->format('Y-m');
        }
    }

    public function updatedBulan(): void
    {
        $this->mount();
    }

    public function render()
    {
        $awal = CarbonImmutable::createFromFormat('!Y-m', $this->bulan, config('app.timezone'));
        $akhir = $awal->endOfMonth();
        $jenis = match ($this->jenis) {
            'harian' => ['harian'],
            'kamis' => ['kamis'],
            'minggu' => ['minggu', 'kerja_bakti'],
            default => null,
        };

        $baris = Penugasan::query()
            ->terbit()
            ->whereDate('tanggal', '>=', $awal->toDateString())
            ->whereDate('tanggal', '<=', $akhir->toDateString())
            ->when($jenis, fn ($q) => $q->whereIn('jenis', $jenis))
            ->get(['user_id', 'status', 'membayar_penugasan_id'])
            ->groupBy('user_id');

        $utang = Penugasan::query()->bintangBelumLunas()
            ->whereDate('tanggal', '<=', $akhir->toDateString())
            ->when($jenis, fn ($q) => $q->whereIn('jenis', $jenis))
            ->selectRaw('user_id, count(*) as jumlah')->groupBy('user_id')->pluck('jumlah', 'user_id');

        $warga = User::query()->with('geng')->where(fn ($q) => $q->where('aktif', true)->orWhereIn('id', $baris->keys()))
            ->orderBy('geng_id')->orderBy('nama')->get()
            ->map(function (User $u) use ($baris, $utang) {
                $b = $baris->get($u->id, collect());
                $asli = $b->whereNull('membayar_penugasan_id');

                return [
                    'user' => $u,
                    'terjadwal' => $b->count(),
                    'selesai' => $b->where('status', StatusPenugasan::Selesai)->count(),
                    'bintang_baru' => $asli->whereIn('status', [StatusPenugasan::Bintang, StatusPenugasan::Lunas])->count(),
                    'lunas' => $asli->where('status', StatusPenugasan::Lunas)->count(),
                    'utang' => (int) ($utang[$u->id] ?? 0),
                ];
            });

        return view('livewire.pengurus.rekap', [
            'awal' => $awal,
            'warga' => $warga,
            'total' => [
                'terjadwal' => $warga->sum('terjadwal'),
                'selesai' => $warga->sum('selesai'),
                'bintang_baru' => $warga->sum('bintang_baru'),
                'utang' => $warga->sum('utang'),
            ],
        ]);
    }
}
