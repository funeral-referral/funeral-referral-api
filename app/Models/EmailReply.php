<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'funeral_home_id',
    'campaign_id',
    'from_email',
    'subject',
    'body',
    'raw',
    'received_at',
])]
class EmailReply extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FuneralHome, $this>
     */
    public function funeralHome(): BelongsTo
    {
        return $this->belongsTo(FuneralHome::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
