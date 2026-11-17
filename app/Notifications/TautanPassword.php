<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email "Lupa password" (berlaku 60 menit), berbahasa Indonesia.
 * Payload antrean dienkripsi karena memuat token.
 */
class TautanPassword extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(object $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting('Halo, '.$notifiable->nama)
            ->subject('Reset password web piket AMKT Putri')
            ->line('Kami menerima permintaan untuk mengganti password akunmu.')
            ->action('Buat password baru', $this->url($notifiable))
            ->line('Link ini berlaku 60 menit. Kalau kamu tidak meminta reset password, abaikan email ini. Password lamamu tetap berlaku.')
            ->salutation('Salam, Pengurus AMKT Putri Mulawarman');
    }
}
