<?php

namespace App\Mail;

use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SavedSearchAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SavedSearch $search, public Collection $listings) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hlídací pes „'.$this->search->name.'“: '.$this->listings->count().' nových inzerátů');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.saved-search');
    }
}
