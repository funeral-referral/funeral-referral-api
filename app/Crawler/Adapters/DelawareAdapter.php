<?php

namespace App\Crawler\Adapters;

use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\Normalizer;

class DelawareAdapter extends SocrataAdapter
{
    public function key(): string
    {
        return 'de-funeral-establishments';
    }

    public function name(): string
    {
        return 'Delaware Division of Professional Regulation - Funeral Establishments';
    }

    public function stateCode(): ?string
    {
        return 'DE';
    }

    protected function endpoint(): string
    {
        return 'https://data.delaware.gov/resource/pjnv-eaih.json';
    }

    protected function query(): array
    {
        return ['$where' => "profession_id='Funeral Services' AND license_type='Funeral Establishment'"];
    }

    protected function map(array $row): ?SourceRecordData
    {
        $name = trim((string) ($row['combined_name'] ?? ''));
        $number = $row['license_no'] ?? null;

        if ($name === '' || $number === null) {
            return null;
        }

        return new SourceRecordData(
            externalId: $number,
            name: $name,
            stateCode: $this->stateOf($row),
            fields: array_filter([
                'city' => Normalizer::city($row['city'] ?? null),
                'postal_code' => Normalizer::postalCode($row['zip_code'] ?? null),
                'license_number' => $number,
                'license_status' => $row['license_status'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            payload: $row,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function stateOf(array $row): string
    {
        $state = strtoupper((string) ($row['state'] ?? ''));

        return strlen($state) === 2 ? $state : 'DE';
    }
}
