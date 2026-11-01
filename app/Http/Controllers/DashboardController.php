<?php

namespace App\Http\Controllers;

use App\Models\ChangeEvent;
use App\Models\FuneralHome;
use App\Models\Source;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const WINDOW_DAYS = 7;

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'totals' => [
                'homes' => FuneralHome::query()->count(),
                'closed' => FuneralHome::query()->whereNotNull('closed_at')->count(),
            ],
            'by_state' => FuneralHome::query()
                ->selectRaw('state_code, count(*) as total, count(closed_at) as closed')
                ->groupBy('state_code')
                ->orderBy('state_code')
                ->get(),
            'by_ownership' => FuneralHome::query()
                ->selectRaw('ownership_type, count(*) as total')
                ->groupBy('ownership_type')
                ->orderByDesc('total')
                ->get(),
            'changes' => [
                'window_days' => self::WINDOW_DAYS,
                'baseline_until' => config('crawler.baseline_until'),
                'by_type' => ChangeEvent::query()
                    ->recent(self::WINDOW_DAYS)
                    ->selectRaw('type, count(*) as total')
                    ->groupBy('type')
                    ->orderByDesc('total')
                    ->get(),
            ],
            'sources' => Source::query()
                ->withCount('records')
                ->with('latestRun')
                ->orderBy('name')
                ->get()
                ->map(fn (Source $source): array => [
                    'id' => $source->id,
                    'key' => $source->key,
                    'name' => $source->name,
                    'type' => $source->type,
                    'state_code' => $source->state_code,
                    'records' => $source->records_count,
                    'last_run' => $source->latestRun === null ? null : [
                        'status' => $source->latestRun->status,
                        'started_at' => $source->latestRun->started_at,
                        'finished_at' => $source->latestRun->finished_at,
                        'records_seen' => $source->latestRun->records_seen,
                        'records_new' => $source->latestRun->records_new,
                        'records_changed' => $source->latestRun->records_changed,
                    ],
                ]),
        ]);
    }
}
