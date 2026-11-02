<?php

namespace App\Http\Resources;

use App\Models\FuneralHome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FuneralHome
 */
class FuneralHomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address_line1' => $this->address_line1,
            'city' => $this->city,
            'state_code' => $this->state_code,
            'county' => $this->county,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'license_number' => $this->license_number,
            'license_status' => $this->license_status,
            'licensed_director' => $this->licensed_director,
            'owner_name' => $this->owner_name,
            'ownership_type' => $this->ownership_type,
            'chain_name' => $this->chain_name,
            'offers_burial' => $this->offers_burial,
            'offers_cremation' => $this->offers_cremation,
            'offers_preneed' => $this->offers_preneed,
            'sells_insurance' => $this->sells_insurance,
            'partner_score' => $this->partner_score,
            'partner_score_breakdown' => $this->partner_score_breakdown,
            'stage' => $this->stage,
            'first_seen_at' => $this->first_seen_at,
            'last_seen_at' => $this->last_seen_at,
            'closed_at' => $this->closed_at,
            'observations' => FieldValueResource::collection($this->whenLoaded('observations')),
            'changes' => ChangeEventResource::collection($this->whenLoaded('changeEvents')),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->user?->name,
                'created_at' => $note->created_at,
            ])),
            'referrals' => $this->whenLoaded('referrals', fn () => $this->referrals->map(fn ($referral) => [
                'id' => $referral->id,
                'referred_on' => $referral->referred_on->toDateString(),
                'reference' => $referral->reference,
                'value_cents' => $referral->value_cents,
                'notes' => $referral->notes,
                'logged_by' => $referral->logger?->name,
            ])),
            'stage_history' => $this->whenLoaded('stageChanges', fn () => $this->stageChanges->map(fn ($change) => [
                'id' => $change->id,
                'from_stage' => $change->from_stage,
                'to_stage' => $change->to_stage,
                'by' => $change->user?->name,
                'created_at' => $change->created_at,
            ])),
            'campaign' => CampaignResource::make($this->whenLoaded('latestCampaign')),
            'replies' => EmailReplyResource::collection($this->whenLoaded('replies')),
        ];
    }
}
