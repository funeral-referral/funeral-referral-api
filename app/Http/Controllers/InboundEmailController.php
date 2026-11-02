<?php

namespace App\Http\Controllers;

use App\Outreach\ReplyLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    /**
     * Webhook for an inbound-email service. Authenticated by a shared secret
     * rather than a user token; with no secret configured it refuses everything.
     */
    public function __invoke(Request $request, ReplyLogger $logger): JsonResponse
    {
        $secret = config('outreach.webhook_secret');

        abort_unless(
            filled($secret) && hash_equals((string) $secret, (string) $request->header('X-Webhook-Secret')),
            403,
        );

        $data = $request->validate([
            'from' => ['required', 'string', 'max:320'],
            'subject' => ['nullable', 'string', 'max:500'],
            'text' => ['nullable', 'string', 'max:50000'],
        ]);

        abort_unless(
            preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $data['from'], $match) === 1,
            422,
            'No sender address found.',
        );

        $reply = $logger->log($match[0], $data['subject'] ?? null, $data['text'] ?? null, $data);

        return response()->json(['id' => $reply->id], 201);
    }
}
