<?php

namespace Tests\Feature;

use App\Enums\OwnershipType;
use App\Models\FuneralHome;
use App\Scoring\OperatorIndex;
use App\Scoring\OwnershipClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipClassifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_chain_brands_are_recognised_by_name(): void
    {
        $home = $this->home(['name' => 'SCI Texas Funeral Services, LLC']);

        $result = $this->classify($home);

        $this->assertSame(OwnershipType::Chain, $result['type']);
        $this->assertSame('SCI / Dignity Memorial', $result['chain_name']);
    }

    public function test_a_chain_website_is_recognised(): void
    {
        $home = $this->home(['name' => 'A L Moore-Grimshaw Mortuaries', 'domain' => 'dignitymemorial.com']);

        $this->assertSame(OwnershipType::Chain, $this->classify($home)['type']);
    }

    public function test_an_operator_with_many_locations_is_a_chain(): void
    {
        $homes = FuneralHome::factory()->count(8)->create($this->bare(['owner_name' => 'Big Group LLC']));

        $result = $this->classify($homes->first());

        $this->assertSame(OwnershipType::Chain, $result['type']);
        $this->assertSame('Big Group LLC', $result['chain_name']);
    }

    public function test_a_small_group_is_family_owned(): void
    {
        $homes = FuneralHome::factory()->count(3)->create($this->bare(['owner_name' => 'Messinger Mortuary & Chapel, Inc.']));

        $result = $this->classify($homes->first());

        $this->assertSame(OwnershipType::FamilyOwned, $result['type']);
        $this->assertStringContainsString('3 locations', $result['reason']);
    }

    public function test_an_individual_owner_is_family_owned(): void
    {
        $home = $this->home(['owner_name' => 'Timothy R. Livingston']);

        $result = $this->classify($home);

        $this->assertSame(OwnershipType::FamilyOwned, $result['type']);
        $this->assertStringContainsString('individual', $result['reason']);
    }

    public function test_a_business_owner_is_not_mistaken_for_a_person(): void
    {
        $home = $this->home(['name' => 'Cedar Funeral Home', 'owner_name' => 'Jason Brubaker, Silver Creek Mortuary, Inc.']);

        $this->assertSame(OwnershipType::Independent, $this->classify($home)['type']);
    }

    public function test_family_names_are_recognised(): void
    {
        $home = $this->home(['name' => 'Smith & Sons Funeral Home', 'phone' => '+15205550100']);

        $this->assertSame(OwnershipType::FamilyOwned, $this->classify($home)['type']);
    }

    public function test_a_single_location_with_contact_data_is_independent(): void
    {
        $home = $this->home(['name' => 'Alder Funeral Home', 'phone' => '+15205550100']);

        $this->assertSame(OwnershipType::Independent, $this->classify($home)['type']);
    }

    public function test_without_any_data_the_ownership_is_unknown(): void
    {
        $home = $this->home(['name' => 'Birch Funeral Home']);

        $this->assertSame(OwnershipType::Unknown, $this->classify($home)['type']);
    }

    /**
     * @return array{type: OwnershipType, chain_name: ?string, reason: string}
     */
    private function classify(FuneralHome $home): array
    {
        return (new OwnershipClassifier)->classify($home, OperatorIndex::build());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function home(array $attributes): FuneralHome
    {
        return FuneralHome::factory()->create($this->bare($attributes));
    }

    /**
     * Attributes with every ownership signal switched off unless a test sets it.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function bare(array $attributes): array
    {
        return $attributes + [
            'owner_name' => null,
            'phone' => null,
            'website' => null,
            'domain' => null,
            'closed_at' => null,
        ];
    }
}
