<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\FuneralHome;
use App\Outreach\CampaignApprover;
use App\Outreach\OutreachGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CampaignStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $campaigns = Campaign::query()
            ->with(['funeralHome', 'messages'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return CampaignResource::collection($campaigns);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $campaign->load(['funeralHome', 'messages', 'approver', 'replies']);

        return new CampaignResource($campaign);
    }

    public function store(FuneralHome $home, OutreachGenerator $generator): JsonResponse
    {
        $campaign = $generator->generate($home)->load('funeralHome');

        return (new CampaignResource($campaign))->response()->setStatusCode(201);
    }

    public function approve(Request $request, Campaign $campaign, CampaignApprover $approver): CampaignResource
    {
        $approver->approve($campaign, $request->user());

        return new CampaignResource($campaign->load(['funeralHome', 'messages', 'approver']));
    }

    public function stop(Campaign $campaign): CampaignResource
    {
        $campaign->stop('manual');

        return new CampaignResource($campaign->load(['funeralHome', 'messages', 'approver']));
    }

    public function destroy(Campaign $campaign): Response
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'campaign' => 'Only a draft can be deleted. Stop a running campaign instead.',
            ]);
        }

        $campaign->delete();

        return response()->noContent();
    }
}
