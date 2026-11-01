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

    /**
     * The register keeps a row per licence history entry, so one licence number can
     * appear several times. Keep the current row: an Active one, or else the one
     * that expires last.
     */
    public function fetch(): iterable
    {
        $current = [];

        foreach (parent::fetch() as $record) {
            $held = $current[$record->externalId] ?? null;

            if ($held === null || $this->rank($record) > $this->rank($held)) {
                $current[$record->externalId] = $record;
            }
        }

        foreach ($current as $record) {
            yield $record;
        }
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

    private function rank(SourceRecordData $record): string
    {
        $active = strcasecmp((string) ($record->fields['license_status'] ?? ''), 'Active') === 0 ? '1' : '0';

        return $active.substr((string) ($record->payload['expiration_date'] ?? ''), 0, 10);
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
