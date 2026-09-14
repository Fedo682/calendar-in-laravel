<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $sender, public Event $event, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Message about: {$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.event-message');
    }
}
