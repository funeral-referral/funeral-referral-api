<?php

namespace App\Outreach;

use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\OutreachVariant;
use App\Models\Campaign;
use App\Models\FuneralHome;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OutreachGenerator
{
    public function __construct(
        private ClaudeMessageWriter $claude,
        private TemplateMessageWriter $template,
    ) {}

    /**
     * Draft a campaign for a home. Nothing is scheduled until a person approves it.
     */
    public function generate(FuneralHome $home): Campaign
    {
        $hasOpenCampaign = $home->campaigns()
            ->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Active])
            ->exists();

        if ($hasOpenCampaign) {
            throw ValidationException::withMessages([
                'campaign' => 'This home already has a campaign in progress. Stop or delete it first.',
            ]);
        }

        $variant = OutreachVariant::forOwnership($home->ownership_type);
        [$messages, $generatedBy] = $this->write($home, $variant);

        return DB::transaction(function () use ($home, $variant, $messages, $generatedBy): Campaign {
            $campaign = $home->campaigns()->create([
                'status' => CampaignStatus::Draft,
                'variant' => $variant,
                'generated_by' => $generatedBy,
            ]);

            foreach (MessageType::cases() as $type) {
                $campaign->messages()->create([
                    'type' => $type,
                    'position' => $type->position(),
                    'subject' => $messages[$type->value]['subject'] ?? null,
                    'body' => $messages[$type->value]['body'],
                    'status' => MessageStatus::Draft,
                ]);
            }

            return $campaign->load('messages');
        });
    }

    /**
     * @return array{0: array<string, array{subject?: string, body: string}>, 1: string}
     */
    private function write(FuneralHome $home, OutreachVariant $variant): array
    {
        if ($this->claude->isConfigured()) {
            try {
                return [$this->claude->write($home, $variant), 'claude'];
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [$this->template->write($home, $variant), 'template'];
    }
}
