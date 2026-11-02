<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmailReplyResource;
use App\Models\EmailReply;
use App\Models\FuneralHome;
use App\Outreach\ReplyLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ReplyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $replies = EmailReply::query()
            ->with('funeralHome')
            ->latest('received_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25);

        return EmailReplyResource::collection($replies);
    }

    /**
     * Log a reply by hand, for answers that did not arrive through the inbound webhook.
     */
    public function store(Request $request, FuneralHome $home, ReplyLogger $logger): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'subject' => ['nullable', 'string', 'max:500'],
            'from_email' => ['nullable', 'email', 'max:320'],
        ]);

        $from = $data['from_email'] ?? $home->email;

        if (blank($from)) {
            throw ValidationException::withMessages([
                'from_email' => 'This home has no email on record, so say which address replied.',
            ]);
        }

        $reply = $logger->log($from, $data['subject'] ?? null, $data['body'], [], $home);

        return (new EmailReplyResource($reply->load('funeralHome')))->response()->setStatusCode(201);
    }
}
