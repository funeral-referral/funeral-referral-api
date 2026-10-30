<?php

namespace App\Crawler\Adapters;

use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\Normalizer;

class ColoradoAdapter extends SocrataAdapter
{
    public function key(): string
    {
        return 'co-dora-funeral-homes';
    }

    public function name(): string
    {
        return 'Colorado DORA - Funeral Homes';
    }

    public function stateCode(): ?string
    {
        return 'CO';
    }

    protected function endpoint(): string
    {
        return 'https://data.colorado.gov/resource/7s5z-vewr.json';
    }

    protected function query(): array
    {
        return ['$where' => "licensetype='FH'"];
    }

    protected function map(array $row): ?SourceRecordData
    {
        $name = trim((string) ($row['entityname'] ?? ''));
        $number = $row['licensenumber'] ?? null;

        if ($name === '' || $number === null) {
            return null;
        }

        $licence = 'FH-'.$number;

        return new SourceRecordData(
            externalId: $licence,
            name: $name,
            stateCode: 'CO',
            fields: array_filter([
                'city' => Normalizer::city($row['city'] ?? null),
                'postal_code' => Normalizer::postalCode($row['mailzipcode'] ?? null),
                'license_number' => $licence,
                'license_status' => $row['licensestatusdescription'] ?? null,
                'offers_preneed' => str_contains(strtolower((string) ($row['specialty'] ?? '')), 'preneed') ? true : null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            payload: $row,
        );
    }
}
