<?php

namespace App\Livewire\Auth;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as AturanPassword;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman dari email "Lupa password" (berlaku 60 menit). Password ini buatan warga sendiri,
 * jadi kewajiban ganti password awal (R2) ikut dimatikan. Setelah berhasil, warga langsung masuk.
 */
#[Layout('components.layouts.polos')]
#[Title('Reset password')]
class AturPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = Str::lower(trim((string) request()->query('email', '')));
    }

    public function simpan()
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', AturanPassword::min(8)],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Ulangi password belum sama.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'harus_ganti_password' => false,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->reset('password', 'password_confirmation');
            $this->addError('email', 'Link ini sudah kedaluwarsa, sudah dipakai, atau email-nya tidak cocok. Minta link baru lewat "Lupa password".');

            return null;
        }

        $user = User::query()->where('email', $this->email)->firstOrFail();
        LogAktivitas::query()->create([
            'user_id' => $user->id,
            'aksi' => 'password.direset',
            'subjek_type' => $user->getMorphClass(),
            'subjek_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);

        if (! $user->aktif) {
            session()->flash('status', 'Password disimpan, tapi akunmu sedang tidak aktif. Hubungi pengurus.');

            return $this->redirectRoute('login');
        }

        Auth::login($user);
        session()->regenerate();
        session()->flash('status', 'Password baru disimpan.');

        return $this->redirect($user->halamanAwal());
    }

    public function render()
    {
        return view('livewire.auth.atur-password');
    }
}
