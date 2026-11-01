<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['key', 'name', 'type', 'state_code', 'enabled', 'config'])]
class Source extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @return HasMany<SourceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(SourceRecord::class);
    }

    /**
     * @return HasOne<CrawlRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(CrawlRun::class)->latestOfMany();
    }
}
