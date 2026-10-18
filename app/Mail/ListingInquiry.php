<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Relayed inquiry: the advertiser sees a masked sender address and replies to a portal relay address. */
class ListingInquiry extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Message $msg) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nový dotaz k inzerátu: '.$this->msg->listing->displayTitle(),
            replyTo: [new Address($this->msg->relayAddress(), $this->msg->sender_name)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.inquiry');
    }
}
