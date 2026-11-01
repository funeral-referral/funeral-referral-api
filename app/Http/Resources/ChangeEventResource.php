<?php

namespace App\Http\Resources;

use App\Models\ChangeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChangeEvent
 */
class ChangeEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'field' => $this->field,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'occurred_at' => $this->occurred_at,
            'home' => $this->whenLoaded('funeralHome', fn (): array => [
                'id' => $this->funeralHome->id,
                'name' => $this->funeralHome->name,
                'city' => $this->funeralHome->city,
                'state_code' => $this->funeralHome->state_code,
            ]),
            'source' => $this->whenLoaded('source', fn (): ?array => $this->source === null ? null : [
                'key' => $this->source->key,
                'name' => $this->source->name,
                'type' => $this->source->type,
            ]),
        ];
    }
}
