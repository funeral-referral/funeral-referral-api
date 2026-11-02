<?php

namespace App\Geo;

use App\Models\FuneralHome;

/**
 * Finds a home's county without touching the crawled county field.
 *
 * A ZIP code maps to the county holding most of its area (Census ZCTA file), and
 * a county name already on record, such as Arizona's, maps by state and name.
 * A match is accepted only when the county sits in the home's own state.
 */
class CountyLocator
{
    private const STATE_FIPS = ['AZ' => '04', 'CO' => '08', 'DE' => '10', 'PA' => '42', 'TX' => '48'];

    /** @var array<string, string> */
    private array $byZip = [];

    /** @var array<string, string> */
    private array $byName = [];

    public function __construct(?string $path = null)
    {
        $path ??= resource_path('data/zip_counties.csv');
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return;
        }

        fgetcsv($handle, null, ',', '"', '');

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            [$zip, $fips, $name] = $row;
            $this->byZip[$zip] = $fips;
            $this->byName[substr($fips, 0, 2).'|'.$this->normalizeName($name)] = $fips;
        }

        fclose($handle);
    }

    public function locate(FuneralHome $home): ?string
    {
        $statePrefix = self::STATE_FIPS[$home->state_code] ?? null;

        if ($statePrefix === null) {
            return null;
        }

        if (preg_match('/^\d{5}/', (string) $home->postal_code, $match) === 1) {
            $fips = $this->byZip[$match[0]] ?? null;

            if ($fips !== null && str_starts_with($fips, $statePrefix)) {
                return $fips;
            }
        }

        return filled($home->county)
            ? ($this->byName[$statePrefix.'|'.$this->normalizeName($home->county)] ?? null)
            : null;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+(county|parish)$/i', '', trim($name))));
    }
}
