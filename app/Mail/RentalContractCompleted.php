<?php

namespace App\Mail;

use App\Models\RentalContract;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Final signed document + audit record for both parties. */
class RentalContractCompleted extends Mailable
{
    public function __construct(public RentalContract $contract, public string $document) {}

    public function envelope(): Envelope { return new Envelope(subject: 'Podepsaná nájemní smlouva – '.$this->contract->d('property.address')); }

    public function content(): Content { return new Content(text: 'mail.contract-completed'); }

    public function attachments(): array
    {
        $audit = view('contracts.audit-text', ['c' => $this->contract])->render();

        return [
            Attachment::fromData(fn () => '<!doctype html><meta charset="utf-8">'.$this->document, 'najemni-smlouva-'.$this->contract->uuid.'.html')->withMime('text/html'),
            Attachment::fromData(fn () => $audit, 'auditni-zaznam-'.$this->contract->uuid.'.txt')->withMime('text/plain'),
        ];
    }
}
