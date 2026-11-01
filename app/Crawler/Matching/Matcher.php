<?php

namespace App\Crawler\Matching;

use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\Normalizer;
use App\Models\FuneralHome;
use Illuminate\Support\Collection;

class Matcher
{
    /**
     * Find the existing funeral home that a source record describes, if any.
     *
     * Rules, strongest first: same licence number; same phone with a similar name;
     * trigram-similar name plus matching domain, locality or map position.
     *
     * @return array{0: FuneralHome, 1: float}|null The home and a 0-1 confidence score.
     */
    public function find(SourceRecordData $record): ?array
    {
        $fields = $record->fields;
        $normalizedName = Normalizer::name($record->name);

        if (! empty($fields['license_number'])) {
            $home = FuneralHome::query()
                ->where('state_code', $record->stateCode)
                ->where('license_number', $fields['license_number'])
                ->first();

            if ($home !== null) {
                return [$home, 1.0];
            }
        }

        foreach ($this->candidates($record->stateCode, $normalizedName, $fields['phone'] ?? null) as $candidate) {
            $similarity = (float) $candidate->name_similarity;

            if ($this->addressConflict($candidate, $fields)) {
                continue;
            }

            if (! empty($fields['phone']) && $candidate->phone === $fields['phone'] && $similarity >= 0.3) {
                return [$candidate, 0.95];
            }

            if (! empty($fields['website']) && $candidate->domain === Normalizer::domain($fields['website']) && $similarity >= 0.4) {
                return [$candidate, 0.9];
            }

            if ($similarity >= 0.6 && $this->sameLocality($candidate, $fields)) {
                return [$candidate, $similarity];
            }

            if ($similarity >= 0.45 && $this->closeOnMap($candidate, $fields)) {
                return [$candidate, 0.85];
            }
        }

        return null;
    }

    /**
     * @return Collection<int, FuneralHome>
     */
    private function candidates(string $stateCode, string $normalizedName, ?string $phone): Collection
    {
        return FuneralHome::query()
            ->select('funeral_homes.*')
            ->selectRaw('similarity(normalized_name, ?) as name_similarity', [$normalizedName])
            ->where('state_code', $stateCode)
            ->where(function ($query) use ($normalizedName, $phone): void {
                $query->whereRaw('normalized_name % ?', [$normalizedName]);

                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->orderByDesc('name_similarity')
            ->limit(10)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function sameLocality(FuneralHome $home, array $fields): bool
    {
        if (! empty($fields['postal_code']) && $home->postal_code === $fields['postal_code']) {
            return true;
        }

        return ! empty($fields['city'])
            && $home->city !== null
            && strcasecmp($home->city, (string) $fields['city']) === 0;
    }

    /**
     * Two records with different street addresses are different locations, even
     * when the name matches, as with a chain's many branches.
     *
     * @param  array<string, mixed>  $fields
     */
    private function addressConflict(FuneralHome $home, array $fields): bool
    {
        $existing = Normalizer::address($home->address_line1);
        $incoming = Normalizer::address($fields['address_line1'] ?? null);

        if ($existing === null || $incoming === null) {
            return false;
        }

        $sameStreetNumber = preg_match('/^\d+/', $existing, $existingNumber) === 1
            && preg_match('/^\d+/', $incoming, $incomingNumber) === 1;

        if ($sameStreetNumber && $existingNumber[0] !== $incomingNumber[0]) {
            return true;
        }

        similar_text($existing, $incoming, $percent);

        return $percent < 85;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function closeOnMap(FuneralHome $home, array $fields): bool
    {
        if ($home->latitude === null || $home->longitude === null) {
            return false;
        }

        if (! isset($fields['latitude'], $fields['longitude'])) {
            return false;
        }

        return abs((float) $home->latitude - (float) $fields['latitude']) < 0.003
            && abs((float) $home->longitude - (float) $fields['longitude']) < 0.004;
    }
}
