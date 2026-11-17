<?php

namespace App\Livewire\Warga;

use App\Enums\Role;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Models\User;
use App\Services\LinkPribadi;
use App\Services\RingkasanWarga;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'profil'])]
#[Title('Profil saya')]
class Profil extends Component
{
    use BeriUmpanBalik;

    /**
     * Kirim ulang link pribadi ke email terdaftar milik warga sendiri. Token lama langsung tidak berlaku.
     * Dibatasi 1 kali per 10 menit supaya tidak disalahgunakan.
     */
    public function kirimUlangLink(LinkPribadi $linkPribadi): void
    {
        $user = auth()->user();
        $kunci = 'kirim-link-sendiri:'.$user->id;

        if (RateLimiter::tooManyAttempts($kunci, 1)) {
            $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);
            $this->toast("Link baru saja dikirim. Coba lagi dalam {$menit} menit.", 'gagal');

            return;
        }

        RateLimiter::hit($kunci, 600);
        $linkPribadi->kirim($user, kirimUlang: true);

        // Token baru juga memutus cookie "ingat saya" lama, jadi sesi ini harus diperbarui supaya tidak ikut keluar.
        auth()->login($user->fresh(), remember: true);

        $this->toast("Link baru sedang dikirim ke {$user->email}. Link lama sudah tidak berlaku.");
    }

    public function render(RingkasanWarga $ringkas)
    {
        $user = auth()->user()->load('geng');

        return view('livewire.warga.profil', [
            'user' => $user,
            'selesaiTotal' => $ringkas->selesaiTotal($user),
            'tataTertib' => User::query()->aktif()->where('role', Role::TataTertib->value)->orderBy('nama')->pluck('nama'),
        ]);
    }
}
