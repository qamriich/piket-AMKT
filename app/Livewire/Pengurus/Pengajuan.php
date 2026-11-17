<?php

namespace App\Livewire\Pengurus;

use App\Enums\StatusIzin;
use App\Enums\StatusPengajuan;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\FormIzin;
use App\Livewire\Concerns\KhususPengurus;
use App\Livewire\Concerns\PutuskanPengajuan;
use App\Models\Izin;
use App\Models\PengajuanGantiBintang;
use App\Models\User;
use App\Services\LayananIzin;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengajuan ganti bintang harian (F8) dan izin (R3, R3a).
 */
#[Layout('components.layouts.pengurus')]
#[Title('Pengajuan')]
class Pengajuan extends Component
{
    use BeriUmpanBalik, FormIzin, KhususPengurus, PutuskanPengajuan;

    #[Url(except: 'menunggu')]
    public string $tampil = 'menunggu'; // menunggu | riwayat | izin

    // Form "Buat izin" oleh pengurus (langsung disetujui).
    public string $izin_user = '';

    public function setTampil(string $tampil): void
    {
        $this->tampil = in_array($tampil, ['menunggu', 'riwayat', 'izin'], true) ? $tampil : 'menunggu';
    }

    public function setujuiIzin(int $id, ?string $catatan, LayananIzin $layanan): void
    {
        $izin = Izin::query()->with('user')->find($id);
        if (! $izin) {
            $this->toast('Izin tidak ditemukan. Muat ulang halaman.', 'gagal');

            return;
        }

        $hasil = null;
        if ($this->coba(function () use ($layanan, $izin, $catatan, &$hasil) {
            $hasil = $layanan->setujui($izin, auth()->user(), $catatan);
        })) {
            $this->toast($this->pesanIzin($izin->user, $hasil));
        }
    }

    public function tolakIzin(int $id, ?string $catatan, LayananIzin $layanan): void
    {
        $izin = Izin::query()->with('user')->find($id);
        if (! $izin) {
            $this->toast('Izin tidak ditemukan. Muat ulang halaman.', 'gagal');

            return;
        }

        $this->coba(fn () => $layanan->tolak($izin, auth()->user(), $catatan), "Izin {$izin->user->namaDepan()} ditolak.");
    }

    public function buatIzin(LayananIzin $layanan): void
    {
        $this->validate(
            ['izin_user' => ['required', 'integer', 'exists:users,id']] + $this->aturanFormIzin(),
            ['izin_user.required' => 'Pilih warganya.'] + $this->pesanFormIzin(),
        );

        $warga = User::query()->findOrFail((int) $this->izin_user);
        $hasil = null;

        $ok = $this->coba(function () use ($layanan, $warga, &$hasil) {
            $hasil = $layanan->buatLangsung(
                $warga,
                $this->waktuIzin($this->izin_mulai),
                $this->waktuIzin($this->izin_selesai),
                $this->izin_alasan,
                auth()->user(),
            )['hasil'];
        });

        if ($ok) {
            $this->reset('izin_user');
            $this->resetFormIzin();
            $this->toast($this->pesanIzin($warga, $hasil));
        }
    }

    /** Ringkasan untuk toast: berapa piket dilepas, siapa penggantinya, dan tugas yang perlu diisi manual. */
    private function pesanIzin(User $warga, array $hasil): string
    {
        $pesan = "Izin {$warga->namaDepan()} disetujui.";
        if ($hasil['dilepas'] !== []) {
            $pesan .= ' '.count($hasil['dilepas']).' piket dilepas.';
        }
        if ($hasil['pengganti'] !== []) {
            $pesan .= ' Pengganti: '.implode(', ', $hasil['pengganti']).'.';
        }
        if ($hasil['tanpa_petugas'] !== []) {
            $pesan .= ' Belum ada petugas: '.implode(', ', $hasil['tanpa_petugas']).'. Isi lewat Atur jadwal.';
        }

        return $pesan;
    }

    public function render()
    {
        $daftar = $this->tampil === 'izin' ? collect() : PengajuanGantiBintang::query()
            ->with(['user.geng', 'penugasanBintang', 'diputuskanOleh'])
            ->when($this->tampil === 'menunggu', fn ($q) => $q->where('status', StatusPengajuan::Menunggu->value)->oldest())
            ->when($this->tampil === 'riwayat', fn ($q) => $q->where('status', '!=', StatusPengajuan::Menunggu->value)->latest('diputuskan_pada'))
            ->limit(100)
            ->get();

        return view('livewire.pengurus.pengajuan', [
            'daftar' => $daftar,
            'jumlahMenunggu' => PengajuanGantiBintang::query()->where('status', StatusPengajuan::Menunggu->value)->count(),
            'izinMenunggu' => $this->tampil === 'izin'
                ? Izin::query()->where('status', StatusIzin::Menunggu->value)->with('user.geng')->orderBy('mulai')->get()
                : collect(),
            'izinRiwayat' => $this->tampil === 'izin'
                ? Izin::query()->where('status', '!=', StatusIzin::Menunggu->value)->with(['user.geng', 'diputuskanOleh'])->latest('mulai')->limit(30)->get()
                : collect(),
            'jumlahIzin' => Izin::query()->where('status', StatusIzin::Menunggu->value)->count(),
            'warga' => $this->tampil === 'izin' ? User::query()->aktif()->orderBy('nama')->get(['id', 'nama']) : collect(),
        ]);
    }
}
