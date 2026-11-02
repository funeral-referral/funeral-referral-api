<?php

namespace App\Http\Resources;

use App\Models\CampaignMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CampaignMessage
 */
class CampaignMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'position' => $this->position,
            'subject' => $this->subject,
            'body' => $this->body,
            'edited' => $this->edited,
            'status' => $this->status,
            'scheduled_for' => $this->scheduled_for,
            'sent_at' => $this->sent_at,
            'sent_to' => $this->sent_to,
        ];
    }
}
