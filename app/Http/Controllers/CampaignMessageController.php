<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Resources\CampaignMessageResource;
use App\Models\CampaignMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CampaignMessageController extends Controller
{
    /**
     * Edit a draft message. Once a campaign is approved its text is locked, so
     * what was approved is exactly what gets sent.
     */
    public function update(Request $request, CampaignMessage $message): CampaignMessageResource
    {
        if ($message->campaign->status !== CampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'message' => 'This campaign is already approved. Stop it and draft a new one to change the text.',
            ]);
        }

        $data = $request->validate([
            'subject' => [$message->type->isEmail() ? 'required' : 'nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message->fill([
            'subject' => $message->type->isEmail() ? $data['subject'] : null,
            'body' => $data['body'],
        ]);

        if ($message->isDirty(['subject', 'body'])) {
            $message->edited = true;
            $message->save();
        }

        return new CampaignMessageResource($message);
    }
}
