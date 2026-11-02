<?php

namespace Tests\Feature;

use App\Enums\OwnershipType;
use App\Enums\Stage;
use App\Geo\CountyLocator;
use App\Models\FuneralHome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_map_requires_authentication(): void
    {
        $this->getJson('/api/map')->assertUnauthorized();
    }

    public function test_a_home_is_placed_in_a_county_by_zip_code(): void
    {
        $locator = new CountyLocator;

        $this->assertSame('04013', $locator->locate(new FuneralHome(['state_code' => 'AZ', 'postal_code' => '85004'])));
        $this->assertSame('04013', $locator->locate(new FuneralHome(['state_code' => 'AZ', 'postal_code' => '85004-1234'])));
    }

    public function test_a_zip_in_another_state_is_not_trusted(): void
    {
        $locator = new CountyLocator;

        $this->assertNull($locator->locate(new FuneralHome(['state_code' => 'CO', 'postal_code' => '85004'])));
        $this->assertNull($locator->locate(new FuneralHome(['state_code' => 'CO', 'postal_code' => null])));
        $this->assertNull($locator->locate(new FuneralHome(['state_code' => 'NV', 'postal_code' => '89101'])));
    }

    public function test_a_county_name_is_used_when_there_is_no_zip(): void
    {
        $locator = new CountyLocator;

        $this->assertSame('04013', $locator->locate(new FuneralHome(['state_code' => 'AZ', 'postal_code' => null, 'county' => 'Maricopa'])));
        $this->assertSame('04013', $locator->locate(new FuneralHome(['state_code' => 'AZ', 'county' => 'maricopa county'])));
        $this->assertNull($locator->locate(new FuneralHome(['state_code' => 'AZ', 'county' => 'Nowhere'])));
    }

    public function test_the_locate_command_stores_the_county_without_touching_the_crawled_county(): void
    {
        $home = FuneralHome::factory()->create(['state_code' => 'AZ', 'postal_code' => '85004', 'county' => 'Crawled County', 'county_fips' => null]);
        $nowhere = FuneralHome::factory()->create(['state_code' => 'TX', 'postal_code' => null, 'county' => null]);

        $this->artisan('homes:locate')->expectsOutputToContain('Located 1 of 2')->assertSuccessful();

        $this->assertSame('04013', $home->refresh()->county_fips);
        $this->assertSame('Crawled County', $home->county);
        $this->assertNull($nowhere->refresh()->county_fips);
    }

    public function test_the_map_counts_open_homes_per_state_and_county(): void
    {
        $this->home('AZ', '04013', 80, Stage::Found);
        $this->home('AZ', '04013', 60, Stage::Contacted);
        $this->home('AZ', '04019', 50, Stage::ActivePartner);
        $this->home('AZ', null, null, Stage::Found);
        $this->home('TX', '48113', 90, Stage::Found);
        FuneralHome::factory()->create(['state_code' => 'AZ', 'county_fips' => '04013', 'closed_at' => now()]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/map')->assertOk();

        $az = collect($response->json('data.states'))->firstWhere('state_code', 'AZ');
        $this->assertSame(4, $az['total']);
        $this->assertSame(1, $az['unplaced']);
        $this->assertSame(2, $az['contacted']);
        $this->assertSame(1, $az['partners']);
        $this->assertSame(63, $az['avg_score']);

        $maricopa = collect($response->json('data.counties'))->firstWhere('fips', '04013');
        $this->assertSame('AZ', $maricopa['state_code']);
        $this->assertSame(2, $maricopa['total']);
        $this->assertSame(70, $maricopa['avg_score']);
        $this->assertCount(3, $response->json('data.counties'));
    }

    public function test_the_map_can_be_filtered_by_ownership_and_score(): void
    {
        $this->home('AZ', '04013', 80, Stage::Found, OwnershipType::Independent);
        $this->home('AZ', '04013', 20, Stage::Found, OwnershipType::Chain);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/map?ownership_type=chain')->assertJsonPath('data.states.0.total', 1);
        $this->getJson('/api/map?min_score=50')->assertJsonPath('data.states.0.total', 1);
        $this->getJson('/api/map?ownership_type=bogus')->assertUnprocessable();
    }

    public function test_homes_can_be_listed_by_county(): void
    {
        $inside = $this->home('AZ', '04013', 70, Stage::Found);
        $this->home('AZ', '04019', 70, Stage::Found);
        Sanctum::actingAs(User::factory()->create());

        $ids = collect($this->getJson('/api/homes?county=04013')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$inside->id], $ids);
        $this->getJson('/api/homes?county=abc')->assertUnprocessable();
    }

    private function home(string $state, ?string $fips, ?int $score, Stage $stage, OwnershipType $ownership = OwnershipType::Independent): FuneralHome
    {
        return FuneralHome::factory()->create([
            'state_code' => $state,
            'county_fips' => $fips,
            'partner_score' => $score,
            'stage' => $stage,
            'ownership_type' => $ownership,
            'closed_at' => null,
        ]);
    }
}
