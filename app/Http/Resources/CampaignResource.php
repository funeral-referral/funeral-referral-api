<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'variant' => $this->variant,
            'generated_by' => $this->generated_by,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'stopped_reason' => $this->stopped_reason,
            'stopped_at' => $this->stopped_at,
            'created_at' => $this->created_at,
            'home' => $this->whenLoaded('funeralHome', fn () => [
                'id' => $this->funeralHome->id,
                'name' => $this->funeralHome->name,
                'city' => $this->funeralHome->city,
                'state_code' => $this->funeralHome->state_code,
                'email' => $this->funeralHome->email,
                'ownership_type' => $this->funeralHome->ownership_type,
                'stage' => $this->funeralHome->stage,
            ]),
            'messages' => CampaignMessageResource::collection($this->whenLoaded('messages')),
            'replies' => EmailReplyResource::collection($this->whenLoaded('replies')),
        ];
    }
}
