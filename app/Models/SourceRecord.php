<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source_id',
    'crawl_run_id',
    'funeral_home_id',
    'external_id',
    'payload',
    'normalized',
    'content_hash',
    'match_score',
    'fetched_at',
])]
class SourceRecord extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'normalized' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FuneralHome, $this>
     */
    public function funeralHome(): BelongsTo
    {
        return $this->belongsTo(FuneralHome::class);
    }
}
