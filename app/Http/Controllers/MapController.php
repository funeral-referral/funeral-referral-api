<?php

namespace App\Http\Controllers;

use App\Enums\OwnershipType;
use App\Models\FuneralHome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class MapController extends Controller
{
    /**
     * Counts per state and per county for open homes, ready to colour a map.
     * Homes with no county on record count towards their state only.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'ownership_type' => ['nullable', Rule::enum(OwnershipType::class)],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $rows = FuneralHome::query()
            ->whereNull('closed_at')
            ->when($filters['ownership_type'] ?? null, fn ($query, string $type) => $query->where('ownership_type', $type))
            ->when($filters['min_score'] ?? null, fn ($query, int $score) => $query->where('partner_score', '>=', $score))
            ->selectRaw('state_code, county_fips, stage, count(*) as total, coalesce(sum(partner_score), 0) as score_sum, count(partner_score) as scored')
            ->groupBy('state_code', 'county_fips', 'stage')
            ->get();

        return response()->json([
            'data' => [
                'states' => $this->summarise($rows, 'state_code')
                    ->map(fn (array $row, string $code): array => ['state_code' => $code, ...$row, 'unplaced' => (int) $rows
                        ->where('state_code', $code)
                        ->whereNull('county_fips')
                        ->sum('total')])
                    ->values(),
                'counties' => $this->summarise($rows->whereNotNull('county_fips'), 'county_fips')
                    ->map(fn (array $row, string $fips): array => [
                        'fips' => $fips,
                        'state_code' => $rows->firstWhere('county_fips', $fips)->state_code,
                        ...$row,
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * @param  Collection<int, FuneralHome>  $rows
     * @return Collection<string, array{total: int, avg_score: int|null, contacted: int, partners: int}>
     */
    private function summarise(Collection $rows, string $key): Collection
    {
        return $rows->groupBy($key)->map(function (Collection $group): array {
            $scored = (int) $group->sum('scored');

            return [
                'total' => (int) $group->sum('total'),
                'avg_score' => $scored === 0 ? null : (int) round($group->sum('score_sum') / $scored),
                'contacted' => (int) $group->filter(fn ($row) => $row->stage->value !== 'found')->sum('total'),
                'partners' => (int) $group->filter(fn ($row) => $row->stage->value === 'active_partner')->sum('total'),
            ];
        });
    }
}
