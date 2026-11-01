<?php

namespace Tests\Feature;

use App\Crawler\Data\SourceRecordData;
use App\Crawler\Matching\Matcher;
use App\Crawler\Support\Normalizer;
use App\Models\FuneralHome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatcherTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Melenyzer Funeral Homes & Cremation Services Inc';

    public function test_the_same_street_address_matches_despite_spelling_differences(): void
    {
        $this->homeAt('825 Fallowfield Avenue');

        $match = (new Matcher)->find($this->recordAt('825 Fallowfield Ave'));

        $this->assertNotNull($match);
    }

    public function test_a_different_street_number_is_a_different_location(): void
    {
        $this->homeAt('226 Fallowfield Avenue');

        $this->assertNull((new Matcher)->find($this->recordAt('825 Fallowfield Ave')));
    }

    private function homeAt(string $address): FuneralHome
    {
        return FuneralHome::factory()->create([
            'name' => self::NAME,
            'normalized_name' => Normalizer::name(self::NAME),
            'address_line1' => $address,
            'city' => 'Charleroi',
            'state_code' => 'PA',
            'postal_code' => '15022',
            'phone' => null,
            'website' => null,
            'domain' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    private function recordAt(string $address): SourceRecordData
    {
        return new SourceRecordData(
            externalId: 'x',
            name: self::NAME,
            stateCode: 'PA',
            fields: ['address_line1' => $address, 'city' => 'Charleroi', 'postal_code' => '15022'],
            payload: [],
        );
    }
}
