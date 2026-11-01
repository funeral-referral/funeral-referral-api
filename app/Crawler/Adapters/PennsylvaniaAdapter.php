<?php

namespace App\Crawler\Adapters;

use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\Normalizer;

class PennsylvaniaAdapter extends ArcGisAdapter
{
    public function key(): string
    {
        return 'pa-funeral-home-layer';
    }

    public function name(): string
    {
        return 'Pennsylvania - Funeral Home layer (pa.gov GIS)';
    }

    public function type(): string
    {
        return 'state_registry';
    }

    public function stateCode(): ?string
    {
        return 'PA';
    }

    protected function layerUrl(): string
    {
        return 'https://services1.arcgis.com/Nifc7wlHaBPig3Q3/arcgis/rest/services/Funeral_Home/FeatureServer/0';
    }

    protected function map(array $attributes, ?array $geometry): ?SourceRecordData
    {
        $name = trim((string) ($attributes['FacilityName'] ?? ''));

        if ($name === '') {
            return null;
        }

        $address = trim((string) ($attributes['AddressLine1'] ?? ''));
        $city = Normalizer::city($attributes['City'] ?? null);
        $zip = (string) ($attributes['ZipCode'] ?? '');

        return new SourceRecordData(
            externalId: $this->externalId('pa', [$address, (string) $city, $zip], $name),
            name: $name,
            stateCode: 'PA',
            fields: array_filter([
                'address_line1' => $address,
                'city' => $city,
                'postal_code' => Normalizer::postalCode($zip),
                'latitude' => $geometry['y'] ?? null,
                'longitude' => $geometry['x'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            payload: $attributes + ['_geometry' => $geometry],
        );
    }
}
