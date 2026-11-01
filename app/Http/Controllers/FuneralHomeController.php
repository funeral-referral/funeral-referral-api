<?php

namespace App\Http\Controllers;

use App\Enums\OwnershipType;
use App\Enums\Stage;
use App\Http\Resources\FuneralHomeResource;
use App\Models\FuneralHome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class FuneralHomeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'state' => ['nullable', 'string', 'size:2'],
            'q' => ['nullable', 'string', 'max:100'],
            'ownership_type' => ['nullable', Rule::enum(OwnershipType::class)],
            'stage' => ['nullable', Rule::enum(Stage::class)],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'sort' => ['nullable', Rule::in(['name', 'state_code', '-first_seen_at'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sort = $filters['sort'] ?? 'name';

        $homes = FuneralHome::query()
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->where('state_code', strtoupper($state)))
            ->when($filters['ownership_type'] ?? null, fn ($query, string $type) => $query->where('ownership_type', $type))
            ->when($filters['stage'] ?? null, fn ($query, string $stage) => $query->where('stage', $stage))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $status === 'closed'
                ? $query->whereNotNull('closed_at')
                : $query->whereNull('closed_at'))
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where(fn ($inner) => $inner
                    ->where('name', 'ilike', $like)
                    ->orWhere('city', 'ilike', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('license_number', 'ilike', $like));
            })
            ->when(
                $sort === '-first_seen_at',
                fn ($query) => $query->orderByDesc('first_seen_at')->orderBy('id'),
                fn ($query) => $query->orderBy($sort)->orderBy('id'),
            )
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return FuneralHomeResource::collection($homes);
    }

    public function show(FuneralHome $home): FuneralHomeResource
    {
        $home->load([
            'observations' => fn ($query) => $query
                ->where('is_current', true)
                ->with('source:id,key,name,type')
                ->orderBy('field')
                ->orderByDesc('observed_at'),
            'changeEvents' => fn ($query) => $query
                ->with('source:id,key,name,type')
                ->latest('occurred_at')
                ->latest('id')
                ->limit(50),
        ]);

        return new FuneralHomeResource($home);
    }
}
