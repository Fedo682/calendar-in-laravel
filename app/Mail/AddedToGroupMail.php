<?php

namespace App\Mail;

use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Implementing ShouldQueue is what turns this from "sent inline, blocking
 * the request" into "handed to the queue" - Mail::send() checks for this
 * interface and, if present, pushes the whole mailable onto the `jobs`
 * table instead of dispatching it immediately. No separate Job class
 * needed; the queue worker (`php artisan queue:work`) picks it up and
 * renders + sends it in the background.
 */
class AddedToGroupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Group $group,
        public string $role,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been added to {$this->group->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.added-to-group',
        );
    }
}
