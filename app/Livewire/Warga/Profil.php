<?php

namespace App\Livewire\Warga;

use App\Enums\Role;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Models\User;
use App\Services\RingkasanWarga;
use Illuminate\Support\Facades\Password;
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
     * Kirim link ganti password ke email terdaftar milik sendiri (sama dengan "Lupa password").
     * Dibatasi 1 kali per 10 menit.
     */
    public function kirimGantiPassword(): void
    {
        $user = auth()->user();
        $kunci = 'ganti-password-sendiri:'.$user->id;

        if (RateLimiter::tooManyAttempts($kunci, 1)) {
            $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);
            $this->toast("Link baru saja dikirim. Coba lagi dalam {$menit} menit.", 'gagal');

            return;
        }

        RateLimiter::hit($kunci, 600);
        $user->sendPasswordResetNotification(Password::broker()->createToken($user));

        $this->toast("Link ganti password sedang dikirim ke {$user->email}. Berlaku 60 menit.");
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
