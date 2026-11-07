<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Týdenní souhrn KPI pro administrátory. */
class WeeklyKpiReport extends Mailable
{
    /** @param array<string,int> $kpi */
    public function __construct(public array $kpi, public bool $heartbeatStale = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Týdenní souhrn ONhost Reality ('.now()->format('j. n. Y').')');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.weekly-kpi');
    }
}
