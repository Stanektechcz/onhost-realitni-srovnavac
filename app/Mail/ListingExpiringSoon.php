<?php

namespace App\Mail;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Připomínka vlastníkovi, že inzerát brzy vyprší. */
class ListingExpiringSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing, public ?string $renewUrl = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Inzerát brzy vyprší: '.$this->listing->displayTitle());
    }

    public function content(): Content
    {
        return new Content(text: 'mail.listing-expiring');
    }
}
