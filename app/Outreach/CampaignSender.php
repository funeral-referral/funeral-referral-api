<?php

namespace App\Outreach;

use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\Stage;
use App\Mail\OutreachMail;
use App\Models\CampaignMessage;
use App\Models\Suppression;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class CampaignSender
{
    /**
     * Send every approved email that is due. Only emails are ever sent; a call
     * script has no send path at all.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendDue(): array
    {
        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        $budget = (int) config('outreach.daily_send_cap') - CampaignMessage::query()
            ->where('status', MessageStatus::Sent->value)
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();

        if ($budget <= 0) {
            return $stats;
        }

        $emailTypes = collect(MessageType::cases())
            ->filter(fn (MessageType $type) => $type->isEmail())
            ->map(fn (MessageType $type) => $type->value)
            ->all();

        $due = CampaignMessage::query()
            ->with('campaign.funeralHome')
            ->where('status', MessageStatus::Scheduled->value)
            ->where('scheduled_for', '<=', now())
            ->whereIn('type', $emailTypes)
            ->whereHas('campaign', fn ($query) => $query->where('status', CampaignStatus::Active->value))
            ->orderBy('scheduled_for')
            ->limit($budget)
            ->get();

        foreach ($due as $message) {
            $claimed = CampaignMessage::query()
                ->whereKey($message->id)
                ->where('status', MessageStatus::Scheduled->value)
                ->update(['status' => MessageStatus::Sending->value]);

            if ($claimed === 0) {
                continue;
            }

            $stats[$this->deliver($message)]++;
        }

        return $stats;
    }

    /**
     * @return 'sent'|'skipped'|'failed'
     */
    private function deliver(CampaignMessage $message): string
    {
        $campaign = $message->campaign->refresh();
        $home = $campaign->funeralHome;

        if ($campaign->status !== CampaignStatus::Active) {
            $message->update(['status' => MessageStatus::Skipped]);

            return 'skipped';
        }

        if (blank($home->email) || Suppression::isSuppressed($home->email)) {
            $message->update(['status' => MessageStatus::Skipped]);
            $campaign->stop(blank($home->email) ? 'no_email' : 'suppressed');

            return 'skipped';
        }

        $recipient = Suppression::normalize($home->email);

        try {
            Mail::to($recipient)->send(new OutreachMail(
                $message,
                URL::signedRoute('unsubscribe', ['campaign' => $campaign->id]),
            ));
        } catch (Throwable $exception) {
            report($exception);

            $message->update(['status' => MessageStatus::Failed]);
            $campaign->stop('send_failed');

            return 'failed';
        }

        $message->update([
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
            'sent_to' => $recipient,
        ]);

        if ($message->type === MessageType::FirstEmail && $home->stage === Stage::Found) {
            $home->moveToStage(Stage::Contacted);
        }

        $stillPending = $campaign->messages()
            ->whereIn('status', [MessageStatus::Scheduled->value, MessageStatus::Sending->value])
            ->exists();

        if (! $stillPending) {
            $campaign->update(['status' => CampaignStatus::Completed]);
        }

        return 'sent';
    }
}
