<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Enums\OutreachVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'funeral_home_id',
    'status',
    'variant',
    'generated_by',
    'approved_by',
    'approved_at',
    'stopped_reason',
    'stopped_at',
])]
class Campaign extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'variant' => OutreachVariant::class,
            'approved_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    /**
     * Whether the sequence can still send or be edited.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [CampaignStatus::Draft, CampaignStatus::Active], true);
    }

    /**
     * End the sequence and cancel every email that has not gone out yet.
     */
    public function stop(string $reason): void
    {
        if (! $this->isOpen()) {
            return;
        }

        $this->update([
            'status' => CampaignStatus::Stopped,
            'stopped_reason' => $reason,
            'stopped_at' => now(),
        ]);

        $this->messages()
            ->whereIn('status', [MessageStatus::Scheduled, MessageStatus::Sending])
            ->update(['status' => MessageStatus::Skipped]);
    }

    /**
     * @return BelongsTo<FuneralHome, $this>
     */
    public function funeralHome(): BelongsTo
    {
        return $this->belongsTo(FuneralHome::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<CampaignMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class)->orderBy('position');
    }

    /**
     * @return HasMany<EmailReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(EmailReply::class);
    }
}
