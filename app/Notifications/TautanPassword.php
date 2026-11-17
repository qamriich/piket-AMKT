<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email "Buat password" (akun baru, berlaku 7 hari) atau "Reset password" (lupa password, berlaku 60 menit).
 * Payload antrean dienkripsi karena memuat token.
 */
class TautanPassword extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public const BUAT = 'buat';

    public const RESET = 'reset';

    public function __construct(public string $token, public string $jenis = self::BUAT) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(object $notifiable): string
    {
        return route($this->jenis === self::BUAT ? 'password.buat' : 'password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)->greeting('Halo, '.$notifiable->nama);

        if ($this->jenis === self::BUAT) {
            return $pesan
                ->subject('Buat password untuk web piket AMKT Putri')
                ->line('Akunmu di web jadwal piket Asrama Mahasiswa Kalimantan Timur Putri Mulawarman Makassar sudah dibuat oleh pengurus.')
                ->line('Tekan tombol di bawah untuk membuat password. Setelah itu kamu bisa login dengan email ini dan password tersebut.')
                ->action('Buat password', $this->url($notifiable))
                ->line('Link ini berlaku 7 hari. Kalau sudah lewat, minta pengurus mengirim ulang atau pakai "Lupa password" di halaman login.')
                ->salutation('Salam, Pengurus AMKT Putri Mulawarman');
        }

        return $pesan
            ->subject('Reset password web piket AMKT Putri')
            ->line('Kami menerima permintaan untuk mengganti password akunmu.')
            ->action('Buat password baru', $this->url($notifiable))
            ->line('Link ini berlaku 60 menit. Kalau kamu tidak meminta reset password, abaikan email ini. Password lamamu tetap berlaku.')
            ->salutation('Salam, Pengurus AMKT Putri Mulawarman');
    }
}
