<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email berisi link pribadi. Payload antrean dienkripsi karena memuat token asli.
 */
class LinkPribadiMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $url,
        public bool $kirimUlang = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kirimUlang
                ? 'Link pribadi baru untuk jadwal piket AMKT Putri'
                : 'Link pribadi jadwal piket AMKT Putri',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.link-pribadi');
    }
}
