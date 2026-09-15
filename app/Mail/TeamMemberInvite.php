<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamMemberInvite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $member,
        public User $owner,
        public string $tempPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pozivnica za BetStudio tim',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invite',
        );
    }
}
