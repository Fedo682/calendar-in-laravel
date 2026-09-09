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
use Illuminate\Support\Collection;

class ConflictReportedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, string>  $conflictingTitles
     */
    public function __construct(
        public User $reporter,
        public Event $event,
        public Collection $conflictingTitles,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Scheduling conflict reported: {$this->event->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.conflict-reported',
        );
    }
}
