<?php

namespace App\Http\Resources;

use App\Models\FieldValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FieldValue
 */
class FieldValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'field' => $this->field,
            'value' => $this->value,
            'observed_at' => $this->observed_at,
            'source' => $this->whenLoaded('source', fn (): array => [
                'key' => $this->source->key,
                'name' => $this->source->name,
                'type' => $this->source->type,
            ]),
        ];
    }
}
