<?php

namespace App\Mail;

use App\Models\RentalContract;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** 6-digit confirmation code for the simple electronic signature. Sent synchronously (time-critical). */
class RentalContractCode extends Mailable
{
    public function __construct(public RentalContract $contract, public string $code) {}

    public function envelope(): Envelope { return new Envelope(subject: 'Kód pro podpis nájemní smlouvy: '.$this->code); }

    public function content(): Content { return new Content(text: 'mail.contract-code'); }
}
