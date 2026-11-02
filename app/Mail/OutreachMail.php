<?php

namespace App\Mail;

use App\Models\CampaignMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class OutreachMail extends Mailable
{
    public function __construct(
        public CampaignMessage $campaignMessage,
        public string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) $this->campaignMessage->subject);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.outreach',
            with: [
                'body' => $this->campaignMessage->body,
                'homeName' => $this->campaignMessage->campaign->funeralHome->name,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'postalAddress' => config('outreach.postal_address'),
            ],
        );
    }

    /**
     * One-click unsubscribe headers (RFC 8058), which mailbox providers look for.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
