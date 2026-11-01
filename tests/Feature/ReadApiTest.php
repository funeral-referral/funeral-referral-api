<?php

namespace Tests\Feature;

use App\Enums\ChangeType;
use App\Models\ChangeEvent;
use App\Models\CrawlRun;
use App\Models\FieldValue;
use App\Models\FuneralHome;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReadApiTest extends TestCase
{
    use RefreshDatabase;

    private Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = Source::query()->create([
            'key' => 'co-test',
            'name' => 'Colorado test board',
            'type' => 'state_board',
            'state_code' => 'CO',
        ]);
    }

    public function test_endpoints_require_authentication(): void
    {
        foreach (['/api/dashboard', '/api/homes', '/api/homes/1', '/api/changes', '/api/crawl-runs'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_dashboard_counts_homes_by_state(): void
    {
        FuneralHome::factory()->count(2)->create(['state_code' => 'CO']);
        FuneralHome::factory()->create(['state_code' => 'TX', 'closed_at' => now()]);
        $this->signIn();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('totals.homes', 3)
            ->assertJsonPath('totals.closed', 1)
            ->assertJsonPath('by_state.0.state_code', 'CO')
            ->assertJsonPath('by_state.0.total', 2)
            ->assertJsonPath('sources.0.key', 'co-test');
    }

    public function test_homes_can_be_filtered_and_searched(): void
    {
        FuneralHome::factory()->create(['name' => 'Alder Funeral Home', 'state_code' => 'CO', 'city' => 'Denver']);
        FuneralHome::factory()->create(['name' => 'Birch Funeral Home', 'state_code' => 'CO', 'city' => 'Aurora', 'closed_at' => now()]);
        FuneralHome::factory()->create(['name' => 'Cedar Funeral Home', 'state_code' => 'TX']);
        $this->signIn();

        $this->getJson('/api/homes?state=co')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/homes?q=alder')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Alder Funeral Home');
        $this->getJson('/api/homes?status=closed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Birch Funeral Home');
        $this->getJson('/api/homes?status=bogus')->assertUnprocessable();
    }

    public function test_home_profile_lists_sourced_observations_and_changes(): void
    {
        $home = FuneralHome::factory()->create(['state_code' => 'CO']);

        foreach ([['+13035550100', true], ['+13035550999', false]] as [$value, $current]) {
            FieldValue::query()->create([
                'funeral_home_id' => $home->id,
                'field' => 'phone',
                'value' => $value,
                'source_id' => $this->source->id,
                'observed_at' => now(),
                'is_current' => $current,
            ]);
        }

        $this->addEvent($home, ChangeType::NewPhone, now());
        $this->signIn();

        $this->getJson("/api/homes/{$home->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.observations')
            ->assertJsonPath('data.observations.0.value', '+13035550100')
            ->assertJsonPath('data.observations.0.source.name', 'Colorado test board')
            ->assertJsonPath('data.changes.0.type', 'new_phone');
    }

    public function test_changes_hide_the_initial_load_unless_requested(): void
    {
        config(['crawler.baseline_until' => now()->subDay()->toDateTimeString()]);
        $home = FuneralHome::factory()->create(['state_code' => 'CO']);

        $this->addEvent($home, ChangeType::NewHome, now()->subDays(2));
        $this->addEvent($home, ChangeType::NewPhone, now()->subHour());
        $this->addEvent($home, ChangeType::NewWebsite, now()->subDays(10));
        $this->signIn();

        $this->getJson('/api/changes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'new_phone')
            ->assertJsonPath('data.0.home.name', $home->name)
            ->assertJsonPath('data.0.source.name', 'Colorado test board');

        $this->getJson('/api/changes?include_initial_load=1')->assertJsonCount(2, 'data');
        $this->getJson('/api/changes?days=30&include_initial_load=1')->assertJsonCount(3, 'data');
        $this->getJson('/api/changes?days=30&include_initial_load=1&type=new_website')->assertJsonCount(1, 'data');
        $this->getJson('/api/changes?days=30&include_initial_load=1&state=TX')->assertJsonCount(0, 'data');
    }

    public function test_crawl_runs_report_status_and_errors(): void
    {
        CrawlRun::query()->create([
            'source_id' => $this->source->id,
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
            'records_seen' => 10,
            'stats' => ['record_errors' => 2],
        ]);
        $this->signIn();

        $this->getJson('/api/crawl-runs')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.record_errors', 2)
            ->assertJsonPath('data.0.source.key', 'co-test');
    }

    public function test_the_first_completed_run_of_a_source_counts_as_its_initial_load(): void
    {
        $home = FuneralHome::factory()->create(['state_code' => 'CO']);
        $failed = $this->runAt(now()->subDays(3), 'failed');
        $first = $this->runAt(now()->subDays(2));
        $second = $this->runAt(now()->subDay());

        $this->addEvent($home, ChangeType::NewHome, now()->subDays(2), $first);
        $this->addEvent($home, ChangeType::NewPhone, now()->subDay(), $second);
        $this->addEvent($home, ChangeType::NewWebsite, now()->subDays(3), $failed);
        $this->signIn();

        $this->getJson('/api/changes')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/changes?include_initial_load=1')->assertJsonCount(3, 'data');
    }

    private function signIn(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function runAt(Carbon $at, string $status = 'completed'): CrawlRun
    {
        return CrawlRun::query()->create([
            'source_id' => $this->source->id,
            'status' => $status,
            'started_at' => $at,
            'finished_at' => $at,
        ]);
    }

    private function addEvent(FuneralHome $home, ChangeType $type, Carbon $at, ?CrawlRun $run = null): void
    {
        ChangeEvent::query()->create([
            'funeral_home_id' => $home->id,
            'type' => $type,
            'source_id' => $this->source->id,
            'crawl_run_id' => $run?->id,
            'occurred_at' => $at,
        ]);
    }
}
