<?php

namespace App\Models;

use App\Enums\OwnershipType;
use App\Enums\Stage;
use Database\Factories\FuneralHomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'normalized_name',
    'address_line1',
    'city',
    'state_code',
    'county',
    'postal_code',
    'latitude',
    'longitude',
    'phone',
    'email',
    'website',
    'domain',
    'social_links',
    'license_number',
    'license_status',
    'licensed_director',
    'ownership_type',
    'chain_name',
    'offers_burial',
    'offers_cremation',
    'offers_preneed',
    'sells_insurance',
    'review_rating',
    'review_count',
    'partner_score',
    'partner_score_breakdown',
    'stage',
    'assigned_to',
    'first_seen_at',
    'last_seen_at',
    'closed_at',
])]
class FuneralHome extends Model
{
    /** @use HasFactory<FuneralHomeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'partner_score_breakdown' => 'array',
            'ownership_type' => OwnershipType::class,
            'stage' => Stage::class,
            'offers_burial' => 'boolean',
            'offers_cremation' => 'boolean',
            'offers_preneed' => 'boolean',
            'sells_insurance' => 'boolean',
            'review_rating' => 'decimal:2',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
