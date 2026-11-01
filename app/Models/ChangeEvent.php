<?php

namespace App\Models;

use App\Enums\ChangeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'funeral_home_id',
    'type',
    'field',
    'old_value',
    'new_value',
    'source_id',
    'crawl_run_id',
    'occurred_at',
])]
class ChangeEvent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => ChangeType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Events from the last few days. The first completed crawl of each source
     * loads every home as a "new home"; that initial load is not news, so it is
     * hidden unless asked for, along with anything before the configured baseline.
     *
     * @param  Builder<ChangeEvent>  $query
     */
    #[Scope]
    protected function recent(Builder $query, int $days = 7, bool $includeInitialLoad = false): void
    {
        $since = now()->subDays($days);
        $baseline = config('crawler.baseline_until');

        if (! $includeInitialLoad) {
            if ($baseline !== null) {
                $since = $since->max(Carbon::parse($baseline));
            }

            $query->where(fn (Builder $inner) => $inner
                ->whereNull('crawl_run_id')
                ->orWhereNotIn('crawl_run_id', CrawlRun::query()
                    ->where('status', 'completed')
                    ->groupBy('source_id')
                    ->selectRaw('min(id)')));
        }

        $query->where('occurred_at', '>=', $since);
    }

    /**
     * @return BelongsTo<FuneralHome, $this>
     */
    public function funeralHome(): BelongsTo
    {
        return $this->belongsTo(FuneralHome::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
