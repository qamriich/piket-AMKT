<?php

namespace App\Livewire\Pengurus;

use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\Geng;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Services\AkunPassword;
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

    /** Kirim ulang email "Buat password" (berlaku 7 hari). Link sebelumnya tidak berlaku lagi. */
    public function kirimBuatPassword(int $wargaId, AkunPassword $akun): void
    {
        $warga = User::query()->findOrFail($wargaId);

        if (! $warga->aktif) {
            $this->toast('Aktifkan akun ini dulu sebelum mengirim email.', 'gagal');

            return;
        }

        $akun->kirimBuatPassword($warga);
        LogAktivitas::catat('warga.email_buat_password', $warga, ['sudah_punya_password' => $warga->punyaPassword()]);

        $this->toast("Email buat password sedang dikirim ke {$warga->email}.");
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
