<?php

namespace App\Crawler\Adapters;

use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\Normalizer;

class ArizonaAdapter extends ArcGisAdapter
{
    public function key(): string
    {
        return 'az-adhs-funeral-homes';
    }

    public function name(): string
    {
        return 'Arizona ADHS - Funeral Homes and Crematoriums';
    }

    public function stateCode(): ?string
    {
        return 'AZ';
    }

    protected function layerUrl(): string
    {
        return 'https://services1.arcgis.com/mpVYz37anSdrK4d8/arcgis/rest/services/FuneralHomesAndCrematoriums/FeatureServer/0';
    }

    protected function map(array $attributes, ?array $geometry): ?SourceRecordData
    {
        $name = trim((string) ($attributes['FuneralHomeName'] ?? ''));

        if ($name === '') {
            return null;
        }

        $address = trim((string) ($attributes['Address'] ?? ''));
        $city = Normalizer::city($attributes['City'] ?? null);
        $zip = (string) ($attributes['ZIP'] ?? '');

        // The board's "DirectorEmail" column holds an email, a URL or a bare domain.
        $contact = rtrim(trim((string) ($attributes['DirectorEmail'] ?? '')), '/');
        $email = Normalizer::email($contact);

        return new SourceRecordData(
            externalId: $this->externalId('az', [$address, (string) $city, $zip], $name),
            name: $name,
            stateCode: 'AZ',
            fields: array_filter([
                'address_line1' => $address,
                'city' => $city,
                'postal_code' => Normalizer::postalCode($zip),
                'county' => trim((string) preg_replace('/\s+County$/i', '', (string) ($attributes['County'] ?? ''))),
                'phone' => Normalizer::phone($attributes['Phone'] ?? null),
                'email' => $email,
                'website' => $email === null ? Normalizer::website($contact) : null,
                'licensed_director' => trim((string) ($attributes['FHDirectorName'] ?? '')),
                'latitude' => $geometry['y'] ?? null,
                'longitude' => $geometry['x'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            payload: $attributes + ['_geometry' => $geometry],
        );
    }
}
