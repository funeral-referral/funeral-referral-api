<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sender identity
    |--------------------------------------------------------------------------
    |
    | Printed in every email. The postal address is required by CAN-SPAM:
    | campaigns cannot be approved until it is set.
    |
    */

    'sender_name' => env('OUTREACH_SENDER_NAME', 'Alex Morgan'),
    'sender_title' => env('OUTREACH_SENDER_TITLE', 'Partnerships'),
    'company' => env('OUTREACH_COMPANY', 'Example Co'),
    'postal_address' => env('OUTREACH_POSTAL_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | What we are offering
    |--------------------------------------------------------------------------
    |
    | One or two sentences, used by the writer in the first email. Edit this to
    | match the real partnership before approving any campaign.
    |
    */

    'offer' => env(
        'OUTREACH_OFFER',
        'We are building a small network of funeral homes we trust, so that when a family we speak with needs one, we can introduce them to a home we know treats people well.',
    ),

    /*
    |--------------------------------------------------------------------------
    | Sequence timing and limits
    |--------------------------------------------------------------------------
    |
    | Days after approval that follow-up 1 and follow-up 2 go out. The daily cap
    | protects the sending domain while it has no reputation.
    |
    */

    'followup_days' => [4, 10],
    'daily_send_cap' => (int) env('OUTREACH_DAILY_CAP', 50),

    /*
    |--------------------------------------------------------------------------
    | Inbound replies and drafting
    |--------------------------------------------------------------------------
    */

    'webhook_secret' => env('OUTREACH_WEBHOOK_SECRET'),
    'model' => env('OUTREACH_MODEL', 'claude-sonnet-5-5'),

];
