<?php

namespace App\Services;

use App\Mail\LinkPribadiMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Membuat dan mengirim link pribadi (bagian 2, mekanisme link pribadi).
 */
class LinkPribadi
{
    /**
     * Membuat token baru (token lama langsung tidak berlaku) lalu mengantrekan email.
     * Mengembalikan URL link pribadi yang baru.
     */
    public function kirim(User $user, bool $kirimUlang = false): string
    {
        $url = $this->buat($user);

        Mail::to($user->email)->queue(new LinkPribadiMail($user, $url, $kirimUlang));

        return $url;
    }

    /** Membuat token baru tanpa mengirim email (dipakai seeder dan perintah artisan). */
    public function buat(User $user): string
    {
        return User::linkPribadi($user->buatTokenBaru());
    }
}
