<?php

namespace App\Scoring;

use App\Enums\OwnershipType;
use App\Models\FuneralHome;

/**
 * An additive score out of 100. Every point appears in the breakdown with its
 * reason, so a reviewer can see exactly why a home ranks where it does.
 */
final class PartnerScorer
{
    /**
     * @return array{score: int, breakdown: list<array{key: string, label: string, points: int, max: int, detail: string}>}
     */
    public function score(FuneralHome $home, int $sourceCount, ?string $ownershipReason = null): array
    {
        $weights = config('partner_score');

        if ($home->closed_at !== null) {
            return [
                'score' => 0,
                'breakdown' => [
                    $this->item('licence', 'Licence', 0, $weights['licence_active'], 'The licence is closed or revoked, so this is not a prospect'),
                ],
            ];
        }

        $type = $home->ownership_type ?? OwnershipType::Unknown;
        $preneed = $home->offers_preneed === true;

        $items = [
            $this->item('ownership', 'Ownership', $weights['ownership'][$type->value], max($weights['ownership']), $ownershipReason ?? "Ownership: {$type->value}"),
            $this->licence($home, $weights),
            $this->presence('phone', 'Phone on file', $home->phone, $weights['phone']),
            $this->presence('website', 'Website', $home->website, $weights['website']),
            $this->presence('email', 'Email', $home->email, $weights['email']),
            $this->presence('director', 'Named licensed director', $home->licensed_director, $weights['director']),
            $this->item('preneed', 'Offers pre-need', $preneed ? $weights['preneed'] : 0, $weights['preneed'], $preneed ? 'Sells pre-need plans' : 'Pre-need not confirmed'),
            $this->presence('address', 'Street address', $home->address_line1, $weights['address']),
            $this->item(
                'corroborated',
                'Confirmed by 2+ sources',
                $sourceCount >= 2 ? $weights['corroborated'] : 0,
                $weights['corroborated'],
                $sourceCount >= 2 ? "{$sourceCount} sources agree this home exists" : 'Only one source so far',
            ),
        ];

        return ['score' => array_sum(array_column($items, 'points')), 'breakdown' => $items];
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array{key: string, label: string, points: int, max: int, detail: string}
     */
    private function licence(FuneralHome $home, array $weights): array
    {
        $status = $home->license_status;

        return match (true) {
            $status === null => $this->item('licence', 'Licence', $weights['licence_listed'], $weights['licence_active'], 'Listed on a state register that publishes no status'),
            strcasecmp($status, 'active') === 0 => $this->item('licence', 'Licence', $weights['licence_active'], $weights['licence_active'], 'Active licence'),
            default => $this->item('licence', 'Licence', 0, $weights['licence_active'], "Licence status: {$status}"),
        };
    }

    /**
     * @return array{key: string, label: string, points: int, max: int, detail: string}
     */
    private function presence(string $key, string $label, mixed $value, int $points): array
    {
        $present = $value !== null && $value !== '';

        return $this->item($key, $label, $present ? $points : 0, $points, $present ? 'On file' : 'Not on file');
    }

    /**
     * @return array{key: string, label: string, points: int, max: int, detail: string}
     */
    private function item(string $key, string $label, int $points, int $max, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'points' => $points, 'max' => $max, 'detail' => $detail];
    }
}
