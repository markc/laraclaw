<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AgentReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $replyBody,
        public string $originalSubject,
        public string $originalMessageId,
        public string $references,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re: '.$this->originalSubject,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: $this->generateMessageId(),
            references: array_filter(explode(' ', $this->references)),
            text: [
                'In-Reply-To' => $this->originalMessageId,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.agent-reply-text',
        );
    }

    protected function generateMessageId(): string
    {
        $address = config('channels.email.address', 'agent@localhost');
        $domain = str_contains($address, '@') ? substr($address, strpos($address, '@') + 1) : $address;

        return '<'.uniqid('claw-', true).'@'.$domain.'>';
    }
}
