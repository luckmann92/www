<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhotoReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $filePath;
    public $fileName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $filePath, string $fileName = 'photo.jpg')
    {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ваше фото готово!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.photo-ready', // Используем markdown шаблон
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromPath(storage_path('app/' . $this->filePath))
                ->as($this->fileName)
                ->withMime('image/jpeg'),
        ];
    }
}
