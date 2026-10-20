<?php

namespace App\Mail;

use App\Models\RentalContract;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RentalContractInvitation extends Mailable
{
    public function __construct(public RentalContract $contract) {}

    public function envelope(): Envelope { return new Envelope(subject: 'Nájemní smlouva k podpisu – '.$this->contract->d('property.address')); }

    public function content(): Content { return new Content(text: 'mail.contract-invitation'); }
}
