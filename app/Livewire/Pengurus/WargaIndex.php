<?php

namespace App\Livewire\Pengurus;

use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\Geng;
use App\Models\LogAktivitas;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * F1 (kelola warga) dan F2 (pindah geng).
 */
#[Layout('components.layouts.pengurus')]
#[Title('Warga & geng')]
class WargaIndex extends Component
{
    use BeriUmpanBalik, KhususPengurus;

    #[Url(except: 'aktif')]
    public string $tampil = 'aktif'; // aktif | nonaktif | semua

    #[Url(as: 'cari', except: '')]
    public string $cari = '';

    public function setTampil(string $tampil): void
    {
        $this->tampil = in_array($tampil, ['aktif', 'nonaktif', 'semua'], true) ? $tampil : 'aktif';
    }

    /** Pindah geng cepat (F2). */
    public function pindahGeng(int $wargaId, $gengId): void
    {
        $warga = User::query()->findOrFail($wargaId);
        $geng = Geng::query()->find((int) $gengId);
        if (! $geng) {
            $this->toast('Geng tidak ditemukan.', 'gagal');

            return;
        }

        $lama = $warga->geng_id;
        $warga->geng_id = $geng->id;
        $warga->save();

        LogAktivitas::catat('warga.pindah_geng', $warga, ['dari' => $lama, 'ke' => $warga->geng_id]);
        $this->toast("{$warga->nama} dipindah ke {$geng->nama}.");
    }

    /** Nonaktifkan atau aktifkan kembali. Warga nonaktif tidak ikut penjadwalan, riwayatnya tetap ada. */
    public function ubahAktif(int $wargaId): void
    {
        $warga = User::query()->findOrFail($wargaId);

        if ($warga->is(auth()->user())) {
            $this->toast('Kamu tidak bisa menonaktifkan akunmu sendiri.', 'gagal');

            return;
        }

        $warga->aktif = ! $warga->aktif;
        $warga->save();

        LogAktivitas::catat($warga->aktif ? 'warga.diaktifkan' : 'warga.dinonaktifkan', $warga);
        $this->toast($warga->aktif
            ? "{$warga->nama} diaktifkan kembali."
            : "{$warga->nama} dinonaktifkan dan tidak akan ikut penjadwalan.");
    }

    /**
     * Reset password (R2): akun kembali ke password awal baru dari pengurus dan wajib diganti lagi
     * saat login berikutnya. Sesi "ingat saya" lama di perangkat lain ikut diputus.
     */
    public function resetPassword(int $wargaId, string $passwordAwal): void
    {
        $warga = User::query()->findOrFail($wargaId);
        $passwordAwal = trim($passwordAwal);

        if ($warga->is(auth()->user())) {
            $this->toast('Untuk akunmu sendiri, ganti password dari halaman Profil.', 'gagal');

            return;
        }
        if (mb_strlen($passwordAwal) < 8 || mb_strlen($passwordAwal) > 100) {
            $this->toast('Password awal minimal 8 karakter.', 'gagal');

            return;
        }

        $warga->aturPasswordAwal($passwordAwal);
        LogAktivitas::catat('warga.reset_password', $warga);

        $this->toast("Password {$warga->namaDepan()} direset. Sampaikan password awalnya; ia wajib menggantinya saat login.");
    }

    public function passwordAcak(): string
    {
        return WargaForm::buatPasswordAcak();
    }

    public function render()
    {
        $users = User::query()
            ->with('geng')
            ->when($this->tampil === 'aktif', fn ($q) => $q->where('aktif', true))
            ->when($this->tampil === 'nonaktif', fn ($q) => $q->where('aktif', false))
            ->when(trim($this->cari) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', '%'.trim($this->cari).'%')
                ->orWhere('email', 'like', '%'.trim($this->cari).'%')
                ->orWhere('no_kamar', 'like', '%'.trim($this->cari).'%')))
            ->orderBy('nama')
            ->get();

        $gengs = Geng::query()->orderBy('urutan')->get();
        $kelompok = $gengs->map(fn (Geng $g) => ['id' => $g->id, 'nama' => $g->nama]);
        $perGeng = $users->groupBy(fn (User $u) => $u->geng_id ?? 0);
        if ($perGeng->has(0)) {
            $kelompok->push(['id' => 0, 'nama' => 'Belum masuk geng']);
        }

        return view('livewire.pengurus.warga-index', [
            'gengs' => $gengs,
            'kelompok' => $kelompok,
            'perGeng' => $perGeng,
            'jumlah' => $users->count(),
            'saya' => auth()->user(),
        ]);
    }
}
