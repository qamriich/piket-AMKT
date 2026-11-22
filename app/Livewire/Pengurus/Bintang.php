<?php

namespace App\Livewire\Pengurus;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\StatusPenugasan;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use App\Services\BintangManual;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Catat bintang manual (misalnya utang bintang dari sebelum aplikasi dipakai) dan lihat semua bintang yang belum lunas.
 */
#[Layout('components.layouts.pengurus')]
#[Title('Bintang')]
class Bintang extends Component
{
    use BeriUmpanBalik, KhususPengurus;

    public $wargaId = '';

    public string $jenis = 'kamis';

    public string $tanggal = '';

    public $tugasId = '';

    public string $catatan = '';

    public function tambah(BintangManual $layanan): void
    {
        $this->validate([
            'wargaId' => ['required', 'integer', 'exists:users,id'],
            'jenis' => ['required', 'in:harian,kamis,minggu,kerja_bakti'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tugasId' => ['nullable', 'integer', 'exists:tugas,id'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [], ['wargaId' => 'warga', 'tugasId' => 'tugas']);

        $warga = User::query()->findOrFail((int) $this->wargaId);
        $jenis = JenisPenugasan::from($this->jenis);
        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $this->tanggal, config('app.timezone'));
        $tugas = $this->tugasId !== '' && $this->tugasId !== null ? Tugas::query()->find((int) $this->tugasId) : null;

        $this->coba(function () use ($layanan, $warga, $jenis, $tanggal, $tugas) {
            $layanan->tambah($warga, $jenis, $tanggal, $tugas, $this->catatan);

            // Warga, jenis, dan tanggal dibiarkan supaya mencatat beberapa bintang berturut-turut lebih cepat.
            $this->reset('tugasId', 'catatan');
        }, "Bintang {$warga->nama} dicatat.");
    }

    public function hapus(int $id, BintangManual $layanan): void
    {
        $bintang = Penugasan::query()->with(['user', 'tugas'])->findOrFail($id);

        $this->coba(fn () => $layanan->hapus($bintang), "Bintang {$bintang->user?->nama} dihapus.");
    }

    public function render()
    {
        $bintang = Penugasan::query()
            ->bintangBelumLunas()
            ->with(['user.geng', 'tugas', 'pembayaran' => fn ($q) => $q->where('status', StatusPenugasan::BayarBintang->value)])
            ->withExists('pengajuanGantiBintang')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        return view('livewire.pengurus.bintang', [
            'daftarWarga' => User::query()->where('aktif', true)->with('geng')->orderBy('geng_id')->orderBy('nama')->get(),
            'daftarTugas' => Tugas::query()->jenis(JenisTugas::KamisMinggu)->urut()->get(),
            'pakaiTugas' => in_array($this->jenis, ['kamis', 'minggu'], true),
            'bintang' => $bintang,
            'hariIni' => CarbonImmutable::now()->toDateString(),
        ]);
    }
}
