<?php

namespace Tests\Feature;

use App\Crawler\Adapters\ArizonaAdapter;
use App\Crawler\Adapters\PennsylvaniaAdapter;
use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\PoliteHttp;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ArcGisAdaptersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['crawler.delay_ms' => 0]);
        PoliteHttp::forgetState();
    }

    public function test_arizona_maps_features_to_records(): void
    {
        Http::fake([
            'services1.arcgis.com/robots.txt' => Http::response('', 403),
            'services1.arcgis.com/mpVYz37anSdrK4d8/*' => Http::response(['features' => [
                [
                    'attributes' => [
                        'FuneralHomeName' => "Jensen's Sierra Vista Mortuary",
                        'FHDirectorName' => 'Pamela Woolard',
                        'DirectorEmail' => 'http://www.jensensmortuary.com/',
                        'Phone' => '520-378-4895',
                        'Address' => '5515 S Highway 92',
                        'City' => 'SIERRA VISTA',
                        'ZIP' => 85650,
                        'County' => 'Cochise County',
                    ],
                    'geometry' => ['x' => -110.2572, 'y' => 31.4688],
                ],
                [
                    'attributes' => [
                        'FuneralHomeName' => 'Greer Mortuary',
                        'DirectorEmail' => 'winslow@greersmortuary.com/',
                        'Address' => '1 Main St',
                        'City' => 'Winslow',
                        'ZIP' => 86047,
                    ],
                    'geometry' => null,
                ],
                ['attributes' => ['FuneralHomeName' => '  ']],
            ]]),
        ]);

        $records = iterator_to_array(app(ArizonaAdapter::class)->fetch(), false);

        $this->assertCount(2, $records);

        $first = $records[0];
        $this->assertSame('AZ', $first->stateCode);
        $this->assertSame("Jensen's Sierra Vista Mortuary", $first->name);
        $this->assertSame([
            'address_line1' => '5515 S Highway 92',
            'city' => 'Sierra Vista',
            'postal_code' => '85650',
            'county' => 'Cochise',
            'phone' => '+15203784895',
            'website' => 'http://www.jensensmortuary.com',
            'licensed_director' => 'Pamela Woolard',
            'latitude' => 31.4688,
            'longitude' => -110.2572,
        ], $first->fields);

        $second = $records[1];
        $this->assertSame('winslow@greersmortuary.com', $second->fields['email']);
        $this->assertArrayNotHasKey('website', $second->fields);
        $this->assertArrayNotHasKey('latitude', $second->fields);
    }

    public function test_pennsylvania_maps_features_and_reports_its_source_type(): void
    {
        Http::fake([
            'services1.arcgis.com/robots.txt' => Http::response('', 403),
            'services1.arcgis.com/Nifc7wlHaBPig3Q3/*' => Http::response(['features' => [[
                'attributes' => ['FacilityName' => 'Feiser Funeral Care', 'AddressLine1' => '306 Harrisburg Street', 'City' => 'East Berlin', 'ZipCode' => '17316', 'District' => '01-006'],
                'geometry' => ['x' => -76.97, 'y' => 39.93],
            ]]]),
        ]);

        $adapter = app(PennsylvaniaAdapter::class);
        $records = iterator_to_array($adapter->fetch(), false);

        $this->assertSame('state_registry', $adapter->type());
        $this->assertSame('PA', $records[0]->stateCode);
        $this->assertSame('East Berlin', $records[0]->fields['city']);
        $this->assertSame('17316', $records[0]->fields['postal_code']);
    }

    public function test_it_pages_through_large_layers(): void
    {
        Http::fake([
            'services1.arcgis.com/robots.txt' => Http::response('', 403),
            'services1.arcgis.com/Nifc7wlHaBPig3Q3/*' => Http::sequence()
                ->push(['exceededTransferLimit' => true, 'features' => [$this->paFeature('Alpha Funeral Home', '1 First St')]])
                ->push(['features' => [$this->paFeature('Beta Funeral Home', '2 Second St')]]),
        ]);

        $records = iterator_to_array(app(PennsylvaniaAdapter::class)->fetch(), false);

        $this->assertSame(['Alpha Funeral Home', 'Beta Funeral Home'], array_map(fn (SourceRecordData $record): string => $record->name, $records));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'resultOffset=1'));
    }

    public function test_features_at_one_address_get_distinct_ids(): void
    {
        Http::fake([
            'services1.arcgis.com/robots.txt' => Http::response('', 403),
            'services1.arcgis.com/Nifc7wlHaBPig3Q3/*' => Http::response(['features' => [
                $this->paFeature('Auman Funeral Home Inc', '247 Penn Street'),
                $this->paFeature('Theo C Auman Inc', '247 Penn Street'),
            ]]),
        ]);

        $records = iterator_to_array(app(PennsylvaniaAdapter::class)->fetch(), false);

        $this->assertNotSame($records[0]->externalId, $records[1]->externalId);
    }

    public function test_an_arcgis_error_fails_the_crawl(): void
    {
        Http::fake([
            'services1.arcgis.com/robots.txt' => Http::response('', 403),
            'services1.arcgis.com/*' => Http::response(['error' => ['code' => 400, 'message' => 'Invalid query']]),
        ]);

        $this->assertThrows(
            fn () => iterator_to_array(app(PennsylvaniaAdapter::class)->fetch(), false),
            RuntimeException::class,
        );
    }

    /**
     * @return array{attributes: array<string, string>, geometry: array<string, float>}
     */
    private function paFeature(string $name, string $address): array
    {
        return [
            'attributes' => ['FacilityName' => $name, 'AddressLine1' => $address, 'City' => 'Reading', 'ZipCode' => '19601'],
            'geometry' => ['x' => -75.9, 'y' => 40.3],
        ];
    }
}
