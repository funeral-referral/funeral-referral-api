<?php

namespace App\Http\Resources;

use App\Models\EmailReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmailReply
 */
class EmailReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_email' => $this->from_email,
            'subject' => $this->subject,
            'body' => $this->body,
            'received_at' => $this->received_at,
            'campaign_id' => $this->campaign_id,
            'home' => $this->whenLoaded('funeralHome', fn () => $this->funeralHome === null ? null : [
                'id' => $this->funeralHome->id,
                'name' => $this->funeralHome->name,
                'city' => $this->funeralHome->city,
                'state_code' => $this->funeralHome->state_code,
            ]),
        ];
    }
}
