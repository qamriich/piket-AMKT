<?php

namespace App\Livewire\Warga;

use App\Enums\Role;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Services\RingkasanWarga;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'profil'])]
#[Title('Profil saya')]
class Profil extends Component
{
    use BeriUmpanBalik;

    public string $password_lama = '';

    public string $password_baru = '';

    public string $password_baru_confirmation = '';

    /** Ganti password kapan saja (PRD bagian 15 R2). Dibatasi 5 percobaan per menit. */
    public function gantiPassword(): void
    {
        $user = auth()->user();
        $kunci = 'ganti-password:'.$user->id;

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('password_lama', 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.');

            return;
        }
        RateLimiter::hit($kunci, 60);

        $this->validate([
            'password_lama' => ['required', 'current_password'],
            'password_baru' => ['required', 'confirmed', PasswordRule::min(8), 'different:password_lama'],
        ], [
            'password_lama.required' => 'Password lama wajib diisi.',
            'password_lama.current_password' => 'Password lama salah.',
            'password_baru.required' => 'Password baru wajib diisi.',
            'password_baru.confirmed' => 'Ulangi password baru belum sama.',
            'password_baru.min' => 'Password baru minimal 8 karakter.',
            'password_baru.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $user->forceFill(['password' => $this->password_baru, 'harus_ganti_password' => false])->save();
        LogAktivitas::catat('password.diganti', $user);
        RateLimiter::clear($kunci);

        $this->reset('password_lama', 'password_baru', 'password_baru_confirmation');
        $this->dispatch('password-diganti');
        $this->toast('Password baru disimpan.');
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
