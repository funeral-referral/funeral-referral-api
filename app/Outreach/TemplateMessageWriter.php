<?php

namespace App\Outreach;

use App\Enums\OutreachVariant;
use App\Models\FuneralHome;

/**
 * Plain templates. Used when no Anthropic key is set or the API call fails,
 * so the outreach flow never depends on a third party being up.
 */
class TemplateMessageWriter implements MessageWriter
{
    public function write(FuneralHome $home, OutreachVariant $variant): array
    {
        return $variant === OutreachVariant::Chain
            ? $this->chain($home)
            : $this->independent($home);
    }

    /**
     * @return array<string, array{subject?: string, body: string}>
     */
    private function independent(FuneralHome $home): array
    {
        $place = $this->place($home);
        $preneed = $home->offers_preneed === true
            ? "I noticed you offer pre-need arrangements, which is where our work overlaps most with yours.\n\n"
            : '';

        return [
            'first_email' => [
                'subject' => "Partnering with {$home->name}",
                'body' => "Hello,\n\nI'm ".config('outreach.sender_name').' with '.config('outreach.company').". I came across {$home->name}{$place} while looking at licensed funeral homes in the area.\n\n"
                    .config('outreach.offer')."\n\n{$preneed}Would you be open to a short call in the next week or two? If email is easier, just reply here.\n\n".$this->signature(),
            ],
            'follow_up_1' => [
                'subject' => "Re: Partnering with {$home->name}",
                'body' => "Hello,\n\nI wanted to follow up on my note from a few days ago. I'd still welcome ten minutes to explain what we have in mind and hear how {$home->name} works with families.\n\nA one-line reply is plenty.\n\n".$this->signature(),
            ],
            'follow_up_2' => [
                'subject' => "Closing the loop with {$home->name}",
                'body' => "Hello,\n\nThis is my last note. If a partnership isn't of interest, no problem at all, and I won't email again. If the timing is just wrong, tell me when to check back.\n\n".$this->signature(),
            ],
            'call_script' => [
                'body' => $this->callScript($home, false),
            ],
        ];
    }

    /**
     * @return array<string, array{subject?: string, body: string}>
     */
    private function chain(FuneralHome $home): array
    {
        $place = $this->place($home);

        return [
            'first_email' => [
                'subject' => "Referral partnership: {$home->name}",
                'body' => "Hello,\n\nI'm ".config('outreach.sender_name').' with '.config('outreach.company').". I'm writing about {$home->name}{$place}.\n\n"
                    .config('outreach.offer')."\n\nIf community or partnership decisions for this location are handled by a regional or corporate office, could you point me to the right person? I'm happy to follow up with them directly.\n\n".$this->signature(),
            ],
            'follow_up_1' => [
                'subject' => "Re: Referral partnership: {$home->name}",
                'body' => "Hello,\n\nA quick follow-up on my earlier note. If you can forward it to whoever handles partnerships or community relations for {$home->name}, I would be grateful.\n\n".$this->signature(),
            ],
            'follow_up_2' => [
                'subject' => "Closing the loop on {$home->name}",
                'body' => "Hello,\n\nThis is my last note. If this isn't something your organisation does, I understand and won't email again.\n\n".$this->signature(),
            ],
            'call_script' => [
                'body' => $this->callScript($home, true),
            ],
        ];
    }

    private function callScript(FuneralHome $home, bool $chain): string
    {
        $ask = $chain
            ? 'Ask: "Who handles referral or community partnerships for this location, or is that decided at a regional office?" Get a name and an email address.'
            : 'Ask: "Could we find ten minutes this week or next to talk it through?"';

        return implode("\n\n", [
            'Call during local business hours. Call by hand; never use an autodialer or text message.',
            'Opening: "Hello, this is '.config('outreach.sender_name').' from '.config('outreach.company').". Is this a good moment for a quick question about {$home->name}?\"",
            'Reason: "'.config('outreach.offer').'"',
            $ask,
            'If they are busy: "No problem. May I send a short email and call back at a better time?"',
            'If they ask to stop being contacted: apologise, end the call, and mark the home "Not interested".',
            'Voicemail (under 25 seconds): "Hello, this is '.config('outreach.sender_name').' from '.config('outreach.company').". I'm reaching out to funeral homes in {$home->city} about a referral partnership. I'll send a short email too. Thank you.\"",
        ]);
    }

    private function place(FuneralHome $home): string
    {
        $parts = array_filter([$home->city, $home->state_code]);

        return $parts === [] ? '' : ' in '.implode(', ', $parts);
    }

    private function signature(): string
    {
        return config('outreach.sender_name')."\n".config('outreach.sender_title').', '.config('outreach.company');
    }
}
