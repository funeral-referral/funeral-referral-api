<?php

namespace App\Outreach;

use App\Enums\Stage;
use App\Models\Campaign;
use App\Models\EmailReply;
use App\Models\FuneralHome;
use App\Models\Suppression;
use Illuminate\Support\Facades\DB;

class ReplyLogger
{
    private const OPT_OUT = '/\b(unsubscribe|remove me|opt[\s-]?out|stop (emailing|contacting|sending)|do not (email|contact)|don\'?t (email|contact))\b/i';

    /**
     * Record a reply, stop any sequence still running for that home, and move
     * the home along the pipeline. A reply that asks to stop also suppresses
     * the address so it can never be emailed again.
     *
     * @param  array<string, mixed>  $raw
     */
    public function log(string $fromEmail, ?string $subject, ?string $body, array $raw = [], ?FuneralHome $home = null): EmailReply
    {
        $email = Suppression::normalize($fromEmail);

        $homes = FuneralHome::query()->whereRaw('lower(email) = ?', [$email])->get();
        $home ??= $homes->first();

        if ($home !== null) {
            $homes->push($home);
        }

        $homes = $homes->unique('id');
        $optedOut = preg_match(self::OPT_OUT, mb_substr(trim($subject.' '.$body), 0, 600)) === 1;

        return DB::transaction(function () use ($email, $subject, $body, $raw, $home, $homes, $optedOut): EmailReply {
            $reply = EmailReply::query()->create([
                'funeral_home_id' => $home?->id,
                'campaign_id' => $home?->campaigns()->latest('id')->value('id'),
                'from_email' => $email,
                'subject' => $subject,
                'body' => $body,
                'raw' => $raw === [] ? null : $raw,
                'received_at' => now(),
            ]);

            Campaign::query()
                ->whereIn('funeral_home_id', $homes->pluck('id'))
                ->get()
                ->each
                ->stop($optedOut ? 'opted_out' : 'replied');

            if ($optedOut) {
                Suppression::add($email, 'opted_out', $home?->id);
            }

            $from = $optedOut ? [Stage::Found, Stage::Contacted, Stage::Replied] : [Stage::Found, Stage::Contacted];

            foreach ($homes as $matched) {
                if (in_array($matched->stage, $from, true)) {
                    $matched->moveToStage($optedOut ? Stage::NotInterested : Stage::Replied);
                }
            }

            return $reply;
        });
    }
}
