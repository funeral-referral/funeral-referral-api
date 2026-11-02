<?php

namespace App\Console\Commands;

use App\Geo\CountyLocator;
use App\Models\FuneralHome;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class LocateHomesCommand extends Command
{
    protected $signature = 'homes:locate';

    protected $description = 'Work out each home\'s county from its ZIP code or county name';

    public function handle(CountyLocator $locator): int
    {
        $located = 0;
        $total = 0;

        FuneralHome::query()->chunkById(500, function (Collection $homes) use ($locator, &$located, &$total): void {
            foreach ($homes as $home) {
                $total++;
                $fips = $locator->locate($home);

                if ($fips !== null) {
                    $located++;
                }

                if ($home->county_fips !== $fips) {
                    $home->update(['county_fips' => $fips]);
                }
            }
        });

        $this->info("Located {$located} of {$total} homes in a county.");

        return self::SUCCESS;
    }
}
