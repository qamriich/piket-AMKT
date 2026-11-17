<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar semua email piket (PRD bagian 8): lewat antrean, berbahasa Indonesia, dan selalu berisi link ke web.
 *
 * Isinya sudah berupa teks saat dibuat (bukan model), supaya email di antrean tetap sama
 * walaupun datanya berubah sebelum terkirim, dan tidak perlu memuat relasi lagi.
 */
abstract class PemberitahuanPiket extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $subjek  Judul email
     * @param  list<string>  $baris  Paragraf isi
     * @param  string  $tombol  Label tombol
     * @param  string  $rute  Nama route tujuan tombol
     */
    public function __construct(
        public string $subjek,
        public array $baris,
        public string $tombol = 'Buka web piket',
        public string $rute = 'dashboard',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject($this->subjek)
            ->greeting('Halo, '.$notifiable->nama);

        foreach ($this->baris as $b) {
            $pesan->line($b);
        }

        return $pesan->action($this->tombol, route($this->rute))
            ->salutation('Salam, Pengurus AMKT Putri Mulawarman');
    }

    /** Untuk test dan log: seluruh isi email dalam satu teks. */
    public function teks(): string
    {
        return $this->subjek."\n".implode("\n", $this->baris);
    }
}
