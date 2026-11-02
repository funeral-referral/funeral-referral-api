<?php

namespace App\Outreach;

use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Campaign;
use App\Models\Suppression;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignApprover
{
    /**
     * Approving is the human gate: only now do the emails get a send time.
     */
    public function approve(Campaign $campaign, User $user): Campaign
    {
        $this->assertCanSend($campaign);

        $days = config('outreach.followup_days');
        $schedule = [
            MessageType::FirstEmail->value => now(),
            MessageType::FollowUpOne->value => now()->addDays($days[0]),
            MessageType::FollowUpTwo->value => now()->addDays($days[1]),
        ];

        DB::transaction(function () use ($campaign, $user, $schedule): void {
            $campaign->update([
                'status' => CampaignStatus::Active,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            foreach ($campaign->messages as $message) {
                if ($message->type->isEmail()) {
                    $message->update([
                        'status' => MessageStatus::Scheduled,
                        'scheduled_for' => $schedule[$message->type->value],
                    ]);
                } else {
                    $message->update(['status' => MessageStatus::Ready]);
                }
            }
        });

        return $campaign->refresh()->load('messages');
    }

    private function assertCanSend(Campaign $campaign): void
    {
        $home = $campaign->funeralHome;

        $problem = match (true) {
            $campaign->status !== CampaignStatus::Draft => 'Only a draft campaign can be approved.',
            blank(config('outreach.postal_address')) => 'Set OUTREACH_POSTAL_ADDRESS first. Every email must carry a postal address.',
            $home->closed_at !== null => 'This home is closed, so there is nobody to contact.',
            blank($home->email) => 'This home has no email address on record, so there is nobody to send to.',
            Suppression::isSuppressed($home->email) => 'This address has opted out and cannot be emailed.',
            $this->addressInUse($campaign) => 'Another active campaign already emails this address.',
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['campaign' => $problem]);
        }
    }

    private function addressInUse(Campaign $campaign): bool
    {
        $email = Suppression::normalize($campaign->funeralHome->email);

        return Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->whereKeyNot($campaign->id)
            ->whereHas('funeralHome', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))
            ->exists();
    }
}
