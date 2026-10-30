<?php

namespace App\Crawler\Adapters;

use App\Crawler\Contracts\SourceAdapter;
use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\Normalizer;
use App\Crawler\Support\PoliteHttp;

class OsmAdapter implements SourceAdapter
{
    public function __construct(private readonly PoliteHttp $http) {}

    public function key(): string
    {
        return 'osm-funeral-directors';
    }

    public function name(): string
    {
        return 'OpenStreetMap - funeral directors';
    }

    public function type(): string
    {
        return 'osm';
    }

    public function stateCode(): ?string
    {
        return null;
    }

    public function fetch(): iterable
    {
        foreach (config('crawler.osm.states') as $state) {
            $elements = $this->http
                ->postForm(config('crawler.osm.endpoint'), ['data' => $this->query($state)])
                ->json('elements', []);

            foreach ($elements as $element) {
                $record = $this->map($element, $state);

                if ($record !== null) {
                    yield $record;
                }
            }
        }
    }

    private function query(string $state): string
    {
        return sprintf(
            '[out:json][timeout:180];area["ISO3166-2"="US-%s"]->.a;(node["shop"="funeral_directors"](area.a);way["shop"="funeral_directors"](area.a););out center tags;',
            $state,
        );
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function map(array $element, string $state): ?SourceRecordData
    {
        $tags = $element['tags'] ?? [];
        $name = trim((string) ($tags['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $street = trim(($tags['addr:housenumber'] ?? '').' '.($tags['addr:street'] ?? ''));

        return new SourceRecordData(
            externalId: $element['type'].'/'.$element['id'],
            name: $name,
            stateCode: $state,
            fields: array_filter([
                'address_line1' => $street,
                'city' => Normalizer::city($tags['addr:city'] ?? null),
                'postal_code' => Normalizer::postalCode($tags['addr:postcode'] ?? null),
                'phone' => Normalizer::phone($tags['phone'] ?? $tags['contact:phone'] ?? null),
                'website' => Normalizer::website($tags['website'] ?? $tags['contact:website'] ?? null),
                'email' => Normalizer::email($tags['email'] ?? $tags['contact:email'] ?? null),
                'latitude' => $element['lat'] ?? $element['center']['lat'] ?? null,
                'longitude' => $element['lon'] ?? $element['center']['lon'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            payload: $element,
        );
    }
}
