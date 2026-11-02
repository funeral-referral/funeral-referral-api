<?php

namespace App\Http\Controllers;

use App\Enums\Stage;
use App\Models\FuneralHome;
use App\Models\Note;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PipelineController extends Controller
{
    /**
     * People a home can be assigned to.
     */
    public function users(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function stage(Request $request, FuneralHome $home): JsonResponse
    {
        $data = $request->validate(['stage' => ['required', Rule::enum(Stage::class)]]);

        $home->moveToStage(Stage::from($data['stage']), $request->user());

        return response()->json(['stage' => $home->stage]);
    }

    public function assign(Request $request, FuneralHome $home): JsonResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')]]);

        $home->update(['assigned_to' => $data['assigned_to']]);

        return response()->json(['assigned_to' => $home->assigned_to]);
    }

    public function storeNote(Request $request, FuneralHome $home): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $note = Note::query()->create([
            'funeral_home_id' => $home->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json([
            'data' => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $request->user()->name,
                'created_at' => $note->created_at,
            ],
        ], 201);
    }

    public function storeReferral(Request $request, FuneralHome $home): JsonResponse
    {
        $data = $request->validate([
            'referred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:200'],
            'value_dollars' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $referral = Referral::query()->create([
            'funeral_home_id' => $home->id,
            'logged_by' => $request->user()->id,
            'referred_on' => $data['referred_on'],
            'reference' => $data['reference'] ?? null,
            'value_cents' => isset($data['value_dollars']) ? (int) round($data['value_dollars'] * 100) : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $referral->id,
                'referred_on' => $referral->referred_on->toDateString(),
                'reference' => $referral->reference,
                'value_cents' => $referral->value_cents,
                'notes' => $referral->notes,
                'logged_by' => $request->user()->name,
            ],
        ], 201);
    }
}
