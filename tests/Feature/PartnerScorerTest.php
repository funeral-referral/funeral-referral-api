<?php

namespace Tests\Feature;

use App\Enums\OwnershipType;
use App\Models\FuneralHome;
use App\Scoring\PartnerScorer;
use Tests\TestCase;

class PartnerScorerTest extends TestCase
{
    public function test_a_fully_documented_independent_home_scores_100(): void
    {
        $home = $this->home([
            'ownership_type' => OwnershipType::Independent,
            'license_status' => 'Active',
            'phone' => '+15205550100',
            'website' => 'https://alder.example',
            'email' => 'info@alder.example',
            'licensed_director' => 'Pat Alder',
            'offers_preneed' => true,
            'address_line1' => '1 Main St',
        ]);

        $result = (new PartnerScorer)->score($home, 2, 'A single location with no sign of chain ownership');

        $this->assertSame(100, $result['score']);
        $this->assertSame(100, array_sum(array_column($result['breakdown'], 'points')));
        $this->assertSame('A single location with no sign of chain ownership', $result['breakdown'][0]['detail']);
    }

    public function test_chains_earn_no_ownership_points(): void
    {
        $result = (new PartnerScorer)->score($this->home(['ownership_type' => OwnershipType::Chain]), 1);

        $this->assertSame(0, $this->points($result, 'ownership'));
    }

    public function test_a_closed_home_scores_zero_and_says_why(): void
    {
        $home = $this->home(['ownership_type' => OwnershipType::Independent, 'closed_at' => now(), 'phone' => '+15205550100']);

        $result = (new PartnerScorer)->score($home, 2);

        $this->assertSame(0, $result['score']);
        $this->assertStringContainsString('closed or revoked', $result['breakdown'][0]['detail']);
    }

    public function test_a_register_listing_without_a_status_earns_partial_licence_points(): void
    {
        $result = (new PartnerScorer)->score($this->home(['license_status' => null]), 1);

        $this->assertSame(12, $this->points($result, 'licence'));
    }

    public function test_an_expired_licence_earns_no_licence_points(): void
    {
        $result = (new PartnerScorer)->score($this->home(['license_status' => 'Expired']), 1);

        $this->assertSame(0, $this->points($result, 'licence'));
        $this->assertSame('Licence status: Expired', $this->detail($result, 'licence'));
    }

    public function test_unconfirmed_pre_need_and_missing_contacts_earn_nothing(): void
    {
        $result = (new PartnerScorer)->score($this->home(['offers_preneed' => null]), 1);

        $this->assertSame(0, $this->points($result, 'preneed'));
        $this->assertSame(0, $this->points($result, 'phone'));
        $this->assertSame(0, $this->points($result, 'corroborated'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function home(array $attributes): FuneralHome
    {
        return FuneralHome::factory()->make($attributes + [
            'ownership_type' => OwnershipType::Unknown,
            'license_status' => null,
            'phone' => null,
            'website' => null,
            'email' => null,
            'licensed_director' => null,
            'offers_preneed' => null,
            'address_line1' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * @param  array{score: int, breakdown: list<array{key: string, label: string, points: int, max: int, detail: string}>}  $result
     */
    private function points(array $result, string $key): int
    {
        return collect($result['breakdown'])->firstWhere('key', $key)['points'];
    }

    /**
     * @param  array{score: int, breakdown: list<array{key: string, label: string, points: int, max: int, detail: string}>}  $result
     */
    private function detail(array $result, string $key): string
    {
        return collect($result['breakdown'])->firstWhere('key', $key)['detail'];
    }
}
