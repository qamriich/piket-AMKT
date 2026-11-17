<?php

namespace App\Livewire\Pengurus;

use App\Enums\StatusIzin;
use App\Enums\StatusPengajuan;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Livewire\Concerns\PutuskanPengajuan;
use App\Models\Izin;
use App\Models\PengajuanGantiBintang;
use App\Models\User;
use App\Services\IzinPulang;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengajuan ganti bintang harian (F8) dan izin pulang (R3).
 */
#[Layout('components.layouts.pengurus')]
#[Title('Pengajuan')]
class Pengajuan extends Component
{
    use BeriUmpanBalik, KhususPengurus, PutuskanPengajuan;

    #[Url(except: 'menunggu')]
    public string $tampil = 'menunggu'; // menunggu | riwayat | izin

    // Form "Buat izin" oleh pengurus (langsung disetujui).
    public string $izin_user = '';

    public string $izin_mulai = '';

    public string $izin_selesai = '';

    public string $izin_alasan = '';

    public function setTampil(string $tampil): void
    {
        $this->tampil = in_array($tampil, ['menunggu', 'riwayat', 'izin'], true) ? $tampil : 'menunggu';
    }

    public function setujuiIzin(int $id, ?string $catatan, IzinPulang $layanan): void
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

    public function tolakIzin(int $id, ?string $catatan, IzinPulang $layanan): void
    {
        $izin = Izin::query()->with('user')->find($id);
        if (! $izin) {
            $this->toast('Izin tidak ditemukan. Muat ulang halaman.', 'gagal');

            return;
        }

        $this->coba(fn () => $layanan->tolak($izin, auth()->user(), $catatan), "Izin {$izin->user->namaDepan()} ditolak.");
    }

    public function buatIzin(IzinPulang $layanan): void
    {
        $this->validate([
            'izin_user' => ['required', 'integer', 'exists:users,id'],
            'izin_mulai' => ['required', 'date_format:Y-m-d'],
            'izin_selesai' => ['required', 'date_format:Y-m-d'],
            'izin_alasan' => ['nullable', 'string', 'max:500'],
        ], [
            'izin_user.required' => 'Pilih warganya.',
            'izin_mulai.required' => 'Isi tanggal berangkat.',
            'izin_selesai.required' => 'Isi tanggal kembali.',
        ]);

        $warga = User::query()->findOrFail((int) $this->izin_user);
        $tz = config('app.timezone');
        $hasil = null;

        $ok = $this->coba(function () use ($layanan, $warga, $tz, &$hasil) {
            $hasil = $layanan->buatLangsung(
                $warga,
                CarbonImmutable::createFromFormat('!Y-m-d', $this->izin_mulai, $tz),
                CarbonImmutable::createFromFormat('!Y-m-d', $this->izin_selesai, $tz),
                $this->izin_alasan,
                auth()->user(),
            )['hasil'];
        });

        if ($ok) {
            $this->reset('izin_user', 'izin_mulai', 'izin_selesai', 'izin_alasan');
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
                ? Izin::query()->where('status', StatusIzin::Menunggu->value)->with('user.geng')->orderBy('tanggal_mulai')->get()
                : collect(),
            'izinRiwayat' => $this->tampil === 'izin'
                ? Izin::query()->where('status', '!=', StatusIzin::Menunggu->value)->with(['user.geng', 'diputuskanOleh'])->latest('tanggal_mulai')->limit(30)->get()
                : collect(),
            'jumlahIzin' => Izin::query()->where('status', StatusIzin::Menunggu->value)->count(),
            'warga' => $this->tampil === 'izin' ? User::query()->aktif()->orderBy('nama')->get(['id', 'nama']) : collect(),
            'hariIni' => CarbonImmutable::now()->toDateString(),
        ]);
    }
}
