<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Login email + password (PRD bagian 2). Dibatasi 5 percobaan gagal per menit; akun nonaktif tidak bisa masuk.
 */
#[Layout('components.layouts.polos')]
#[Title('Masuk')]
class Login extends Component
{
    public const MAKS_PERCOBAAN = 5;

    public string $email = '';

    public string $password = '';

    public bool $ingat = false;

    public function masuk()
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $kunci = 'login:'.$this->email.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'aktif' => true], $this->ingat)) {
            RateLimiter::hit($kunci, 60);
            $this->reset('password');

            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        RateLimiter::clear($kunci);
        session()->regenerate();

        return $this->redirectIntended(Auth::user()->halamanAwal());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
