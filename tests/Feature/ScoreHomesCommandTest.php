<?php

namespace Tests\Feature;

use App\Enums\OwnershipType;
use App\Models\FuneralHome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreHomesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_and_scores_every_home(): void
    {
        $chain = FuneralHome::factory()->create($this->bare(['name' => 'SCI Texas Funeral Services, LLC']));
        $independent = FuneralHome::factory()->create($this->bare(['name' => 'Alder Funeral Home', 'phone' => '+15205550100']));
        $closed = FuneralHome::factory()->create($this->bare(['name' => 'Birch Funeral Home', 'phone' => '+15205550111', 'closed_at' => now()]));

        $this->artisan('homes:score')->assertSuccessful();

        $this->assertSame(OwnershipType::Chain, $chain->fresh()->ownership_type);
        $this->assertSame('SCI / Dignity Memorial', $chain->fresh()->chain_name);
        $this->assertSame(OwnershipType::Independent, $independent->fresh()->ownership_type);
        $this->assertGreaterThan($chain->fresh()->partner_score, $independent->fresh()->partner_score);
        $this->assertNotEmpty($independent->fresh()->partner_score_breakdown);
        $this->assertSame(0, $closed->fresh()->partner_score);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function bare(array $attributes): array
    {
        return $attributes + [
            'owner_name' => null,
            'phone' => null,
            'website' => null,
            'domain' => null,
            'closed_at' => null,
            'license_status' => null,
        ];
    }
}
