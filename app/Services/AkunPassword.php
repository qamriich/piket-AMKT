<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TautanPassword;
use Illuminate\Support\Facades\Password;

/**
 * Email "Buat password" untuk akun baru atau yang dikirim ulang pengurus (PRD bagian 2 poin 2 dan 5).
 */
class AkunPassword
{
    /** Membuat token 7 hari (token lama tidak berlaku), mengantrekan email, dan mengembalikan link-nya. */
    public function kirimBuatPassword(User $user): string
    {
        $notifikasi = new TautanPassword($this->tokenBuat($user), TautanPassword::BUAT);
        $user->notify($notifikasi);

        return $notifikasi->url($user);
    }

    /** Link "Buat password" tanpa mengirim email (untuk perintah artisan). */
    public function linkBuatPassword(User $user): string
    {
        return (new TautanPassword($this->tokenBuat($user), TautanPassword::BUAT))->url($user);
    }

    private function tokenBuat(User $user): string
    {
        return Password::broker('buat')->createToken($user);
    }
}
