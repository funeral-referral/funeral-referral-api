<?php

namespace App\Scoring;

use App\Enums\OwnershipType;
use App\Models\FuneralHome;

/**
 * Sorts a home into chain, family-owned, independent or unknown, and says why.
 * "Independent" only means no sign of chain ownership was found, and it is only
 * claimed when there is some owner, website or phone data to look at.
 */
final class OwnershipClassifier
{
    /**
     * @return array{type: OwnershipType, chain_name: ?string, reason: string}
     */
    public function classify(FuneralHome $home, OperatorIndex $operators): array
    {
        $haystack = strtolower(implode(' | ', array_filter([$home->name, $home->owner_name, $home->domain])));

        foreach (config('ownership.chains') as $chain => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('#'.$pattern.'#i', $haystack) === 1) {
                    return $this->result(OwnershipType::Chain, $chain, "Matches the {$chain} brand");
                }
            }
        }

        $locations = $operators->locations($home);

        if ($locations >= config('ownership.chain_location_threshold')) {
            return $this->result(OwnershipType::Chain, $home->owner_name ?? $home->domain, "The same operator runs {$locations} locations");
        }

        if ($locations >= 2) {
            return $this->result(OwnershipType::FamilyOwned, null, "A regional operator with {$locations} locations");
        }

        if ($this->isIndividual($home->owner_name)) {
            return $this->result(OwnershipType::FamilyOwned, null, "The owner is an individual ({$home->owner_name})");
        }

        if (preg_match('#'.config('ownership.family_pattern').'#i', $home->name) === 1) {
            return $this->result(OwnershipType::FamilyOwned, null, 'The name suggests a family business');
        }

        if ($home->owner_name !== null || $home->domain !== null || $home->phone !== null) {
            return $this->result(OwnershipType::Independent, null, 'A single location with no sign of chain ownership');
        }

        return $this->result(OwnershipType::Unknown, null, 'No owner, website or phone on record to judge from');
    }

    private function isIndividual(?string $owner): bool
    {
        if ($owner === null) {
            return false;
        }

        if (preg_match('#'.config('ownership.corporate_owner_pattern').'#i', $owner) === 1) {
            return false;
        }

        return preg_match("/^[\p{L}.'-]+(\s+[\p{L}.'-]+){1,3}$/u", trim($owner)) === 1;
    }

    /**
     * @return array{type: OwnershipType, chain_name: ?string, reason: string}
     */
    private function result(OwnershipType $type, ?string $chainName, string $reason): array
    {
        return ['type' => $type, 'chain_name' => $chainName, 'reason' => $reason];
    }
}
