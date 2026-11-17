<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lupa password: mengirim email reset (berlaku 60 menit). Jawabannya selalu sama, supaya tidak bisa dipakai
 * untuk menebak email mana yang terdaftar.
 */
#[Layout('components.layouts.polos')]
#[Title('Lupa password')]
class LupaPassword extends Component
{
    public string $email = '';

    public bool $terkirim = false;

    public function kirim(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate(['email' => ['required', 'email']], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        $kunci = 'lupa-password:'.request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('email', 'Terlalu banyak permintaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.');

            return;
        }
        RateLimiter::hit($kunci, 60);

        // Hanya akun aktif yang dikirimi email. Throttle bawaan broker: 1 email per 60 detik per akun.
        Password::broker()->sendResetLink(['email' => $this->email, 'aktif' => true]);

        $this->terkirim = true;
    }

    public function render()
    {
        return view('livewire.auth.lupa-password');
    }
}
