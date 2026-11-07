<?php

namespace App\Mail;

use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class SavedSearchAlert extends Mailable
{
    use Queueable, SerializesModels;

    public string $unsubscribeUrl;

    public function __construct(public SavedSearch $search, public Collection $listings, public ?int $total = null)
    {
        $this->total ??= $listings->count();
        $this->unsubscribeUrl = URL::signedRoute('searches.unsubscribe', ['search' => $search->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hlídací pes „'.$this->search->name.'“: '.$this->total.' nových inzerátů');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>']);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.saved-search');
    }
}
