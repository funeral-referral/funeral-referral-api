<?php

namespace App\Scoring;

use App\Models\FuneralHome;

/**
 * How many open locations each operator has, counted by owner name and by website
 * domain across every state, so a chain is recognised wherever it appears.
 */
final class OperatorIndex
{
    /**
     * @param  array<string, int>  $owners  Lower-case owner name to location count.
     * @param  array<string, int>  $domains  Website domain to location count.
     */
    private function __construct(private readonly array $owners, private readonly array $domains) {}

    public static function build(): self
    {
        $owners = FuneralHome::query()
            ->whereNull('closed_at')
            ->whereNotNull('owner_name')
            ->selectRaw('lower(owner_name) as k, count(*) as total')
            ->groupByRaw('lower(owner_name)')
            ->pluck('total', 'k')
            ->all();

        $domains = FuneralHome::query()
            ->whereNull('closed_at')
            ->whereNotNull('domain')
            ->whereNotIn('domain', config('ownership.generic_domains'))
            ->selectRaw('domain as k, count(*) as total')
            ->groupBy('domain')
            ->pluck('total', 'k')
            ->all();

        return new self($owners, $domains);
    }

    public function locations(FuneralHome $home): int
    {
        return max(
            $home->owner_name === null ? 0 : ($this->owners[strtolower($home->owner_name)] ?? 0),
            $home->domain === null ? 0 : ($this->domains[$home->domain] ?? 0),
        );
    }
}
