<?php

namespace App\Crawler\Data;

final readonly class SourceRecordData
{
    /**
     * @param  array<string, mixed>  $fields  Normalized funeral home fields (city, phone, license_number, ...).
     * @param  array<string, mixed>  $payload  The raw record exactly as the source returned it.
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public string $stateCode,
        public array $fields,
        public array $payload,
    ) {}
}
