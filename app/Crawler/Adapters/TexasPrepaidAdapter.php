<?php

namespace App\Crawler\Adapters;

use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\Normalizer;

class TexasPrepaidAdapter extends SocrataAdapter
{
    private const FUNERAL_NAME_PATTERN = '/funeral|mortuar|chapel|cremat|burial/i';

    public function key(): string
    {
        return 'tx-prepaid-funeral-sellers';
    }

    public function name(): string
    {
        return 'Texas - Prepaid Funeral Contract Sellers';
    }

    public function stateCode(): ?string
    {
        return 'TX';
    }

    protected function endpoint(): string
    {
        return 'https://data.texas.gov/resource/p576-5udu.json';
    }

    protected function query(): array
    {
        return [];
    }

    protected function map(array $row): ?SourceRecordData
    {
        $name = trim((string) ($row['entity'] ?? ''));

        if ($name === '' || preg_match(self::FUNERAL_NAME_PATTERN, $name) !== 1) {
            return null;
        }

        $address = trim((string) ($row['address'] ?? ''));

        return new SourceRecordData(
            externalId: implode('|', [$row['charter_license'] ?? '', $row['location_type'] ?? '', $address]),
            name: $name,
            stateCode: 'TX',
            fields: array_filter([
                'address_line1' => $address,
                'city' => Normalizer::city($row['city'] ?? null),
                'phone' => Normalizer::phone($row['phone'] ?? null),
                'offers_preneed' => true,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            payload: $row,
        );
    }
}
