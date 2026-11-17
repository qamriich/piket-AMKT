<?php

namespace App\Livewire\Pengurus;

use App\Enums\Role;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\Geng;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Services\LinkPribadi;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * F1: tambah dan ubah warga. Aturannya sama dengan sebelumnya (WargaController).
 */
#[Layout('components.layouts.pengurus')]
class WargaForm extends Component
{
    use BeriUmpanBalik, KhususPengurus;

    #[Locked]
    public ?int $wargaId = null;

    public string $nama = '';

    public string $email = '';

    public string $no_kamar = '';

    public $geng_id = '';

    public string $role = 'warga';

    public bool $wajib_diperiksa = false;

    public bool $kirim_link = true;

    public function mount(?User $warga = null): void
    {
        if ($warga?->exists) {
            $this->wargaId = $warga->id;
            $this->nama = $warga->nama;
            $this->email = $warga->email;
            $this->no_kamar = (string) $warga->no_kamar;
            $this->geng_id = $warga->geng_id ?? '';
            $this->role = $warga->role->value;
            $this->wajib_diperiksa = $warga->wajib_diperiksa;
        }
    }

    private function warga(): ?User
    {
        return $this->wargaId ? User::query()->findOrFail($this->wargaId) : null;
    }

    public function simpan(LinkPribadi $linkPribadi)
    {
        $warga = $this->warga();

        // Normalisasi dulu supaya cek email unik tidak tertipu huruf besar/kecil.
        $this->email = strtolower(trim($this->email));

        $data = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($warga?->id)],
            'no_kamar' => ['nullable', 'string', 'max:20'],
            'geng_id' => ['required', 'exists:gengs,id'],
            'role' => ['required', Rule::enum(Role::class)],
            'wajib_diperiksa' => ['boolean'],
        ], [], [
            'no_kamar' => 'nomor kamar',
            'geng_id' => 'geng',
            'wajib_diperiksa' => 'wajib diperiksa',
        ]);
        $data['no_kamar'] = $data['no_kamar'] !== '' ? $data['no_kamar'] : null;
        $data['geng_id'] = (int) $data['geng_id'];

        if ($warga === null) {
            $baru = new User($data);
            $baru->aktif = true;
            $baru->save();
            LogAktivitas::catat('warga.dibuat', $baru, $data);

            $pesan = "Akun {$baru->nama} berhasil dibuat.";
            if ($this->kirim_link) {
                $linkPribadi->kirim($baru);
                LogAktivitas::catat('warga.link_dikirim', $baru);
                $pesan .= " Link pribadi sedang dikirim ke {$baru->email}.";
            }

            session()->flash('status', $pesan);

            return $this->redirectRoute('pengurus.warga.index', navigate: true);
        }

        if ($warga->is(auth()->user()) && $data['role'] === Role::Warga->value) {
            $this->toast('Kamu tidak bisa menurunkan role akunmu sendiri menjadi warga.', 'gagal');

            return null;
        }

        $warga->fill($data);
        $berubah = $warga->getDirty();
        $warga->save();

        if ($berubah) {
            LogAktivitas::catat('warga.diubah', $warga, $berubah);
        }

        session()->flash('status', "Data {$warga->nama} berhasil disimpan.");

        return $this->redirectRoute('pengurus.warga.index', navigate: true);
    }

    public function render()
    {
        $warga = $this->warga();

        return view('livewire.pengurus.warga-form', [
            'baru' => $warga === null,
            'warga' => $warga,
            'gengs' => Geng::query()->orderBy('urutan')->get(),
        ])->title($warga ? 'Ubah '.$warga->nama : 'Tambah warga');
    }
}
