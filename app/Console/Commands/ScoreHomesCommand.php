<?php

namespace App\Console\Commands;

use App\Models\FieldValue;
use App\Models\FuneralHome;
use App\Scoring\OperatorIndex;
use App\Scoring\OwnershipClassifier;
use App\Scoring\PartnerScorer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ScoreHomesCommand extends Command
{
    protected $signature = 'homes:score';

    protected $description = 'Classify ownership and recompute every partner score';

    public function handle(OwnershipClassifier $classifier, PartnerScorer $scorer): int
    {
        $operators = OperatorIndex::build();

        $sourceCounts = FieldValue::query()
            ->where('is_current', true)
            ->groupBy('funeral_home_id')
            ->selectRaw('funeral_home_id, count(distinct source_id) as total')
            ->pluck('total', 'funeral_home_id');

        $scored = 0;

        FuneralHome::query()->chunkById(500, function (Collection $homes) use ($classifier, $scorer, $operators, $sourceCounts, &$scored): void {
            foreach ($homes as $home) {
                $ownership = $classifier->classify($home, $operators);
                $home->ownership_type = $ownership['type'];
                $home->chain_name = $ownership['chain_name'];

                $result = $scorer->score($home, (int) ($sourceCounts[$home->id] ?? 0), $ownership['reason']);
                $home->partner_score = $result['score'];
                $home->partner_score_breakdown = $result['breakdown'];

                $home->save();
                $scored++;
            }
        });

        $this->info("Scored {$scored} homes.");

        return self::SUCCESS;
    }
}
