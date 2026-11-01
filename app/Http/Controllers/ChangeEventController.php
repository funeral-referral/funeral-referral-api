<?php

namespace App\Http\Controllers;

use App\Enums\ChangeType;
use App\Http\Resources\ChangeEventResource;
use App\Models\ChangeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ChangeEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'type' => ['nullable', Rule::enum(ChangeType::class)],
            'state' => ['nullable', 'string', 'size:2'],
            'include_initial_load' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = ChangeEvent::query()
            ->recent($filters['days'] ?? 7, $request->boolean('include_initial_load'))
            ->with(['funeralHome:id,name,city,state_code', 'source:id,key,name,type'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->whereHas(
                'funeralHome',
                fn ($home) => $home->where('state_code', strtoupper($state)),
            ))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return ChangeEventResource::collection($events);
    }
}
