<?php

namespace App\Http\Resources;

use App\Models\CrawlRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrawlRun
 */
class CrawlRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'records_seen' => $this->records_seen,
            'records_new' => $this->records_new,
            'records_changed' => $this->records_changed,
            'record_errors' => $this->stats['record_errors'] ?? 0,
            'error' => $this->error,
            'source' => $this->whenLoaded('source', fn (): array => [
                'key' => $this->source->key,
                'name' => $this->source->name,
                'type' => $this->source->type,
                'state_code' => $this->source->state_code,
            ]),
        ];
    }
}
