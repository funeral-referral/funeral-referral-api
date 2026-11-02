<?php

namespace App\Outreach;

use App\Models\Campaign;
use App\Models\Suppression;

class Unsubscriber
{
    /**
     * Honour an unsubscribe: suppress every address this campaign emailed (and
     * the home's current one) and stop all sequences that would reach them.
     */
    public function unsubscribe(Campaign $campaign): void
    {
        $home = $campaign->funeralHome;

        $addresses = $campaign->messages()
            ->whereNotNull('sent_to')
            ->pluck('sent_to')
            ->push($home->email)
            ->filter()
            ->map(fn (string $email) => Suppression::normalize($email))
            ->unique();

        foreach ($addresses as $address) {
            Suppression::add($address, 'unsubscribed', $home->id);

            Campaign::query()
                ->whereHas('funeralHome', fn ($query) => $query->whereRaw('lower(email) = ?', [$address]))
                ->get()
                ->each
                ->stop('unsubscribed');
        }

        $campaign->refresh()->stop('unsubscribed');
    }
}
