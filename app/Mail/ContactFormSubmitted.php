<?php

namespace App\Mail;

use App\Http\Controllers\PagesController;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Kopie zprávy z /kontakt na provozní adresu (reality.contact_email). */
class ContactFormSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $msg) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kontaktní formulář: '.(PagesController::TOPICS[$this->msg->topic] ?? $this->msg->topic),
            replyTo: [new Address($this->msg->email, $this->msg->name)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-form');
    }
}
