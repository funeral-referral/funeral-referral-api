<?php

namespace Tests\Feature;

use App\Enums\Stage;
use App\Models\FuneralHome;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
        $this->putJson('/api/homes/1/stage', [])->assertUnauthorized();
        $this->putJson('/api/homes/1/assignee', [])->assertUnauthorized();
        $this->postJson('/api/homes/1/notes', [])->assertUnauthorized();
        $this->postJson('/api/homes/1/referrals', [])->assertUnauthorized();
    }

    public function test_moving_a_home_through_stages_records_who_moved_it(): void
    {
        $home = FuneralHome::factory()->create(['stage' => Stage::Found]);
        $user = $this->signIn();

        $this->putJson("/api/homes/{$home->id}/stage", ['stage' => 'meeting'])
            ->assertOk()
            ->assertJsonPath('stage', 'meeting');

        $this->assertSame(Stage::Meeting, $home->refresh()->stage);
        $this->assertDatabaseHas('stage_changes', [
            'funeral_home_id' => $home->id,
            'user_id' => $user->id,
            'from_stage' => 'found',
            'to_stage' => 'meeting',
        ]);

        $this->putJson("/api/homes/{$home->id}/stage", ['stage' => 'meeting'])->assertOk();
        $this->assertDatabaseCount('stage_changes', 1);

        $this->putJson("/api/homes/{$home->id}/stage", ['stage' => 'bogus'])->assertUnprocessable();
    }

    public function test_a_home_can_be_assigned_and_unassigned(): void
    {
        $home = FuneralHome::factory()->create();
        $agent = User::factory()->create();
        $this->signIn();

        $this->putJson("/api/homes/{$home->id}/assignee", ['assigned_to' => $agent->id])
            ->assertOk()
            ->assertJsonPath('assigned_to', $agent->id);

        $this->putJson("/api/homes/{$home->id}/assignee", ['assigned_to' => 9999])->assertUnprocessable();

        $this->putJson("/api/homes/{$home->id}/assignee", ['assigned_to' => null])->assertOk();
        $this->assertNull($home->refresh()->assigned_to);
    }

    public function test_notes_are_saved_with_their_author(): void
    {
        $home = FuneralHome::factory()->create();
        $user = $this->signIn();

        $this->postJson("/api/homes/{$home->id}/notes", ['body' => 'Spoke to the owner, call back Monday.'])
            ->assertCreated()
            ->assertJsonPath('data.author', $user->name);

        $this->assertDatabaseHas('notes', ['funeral_home_id' => $home->id, 'user_id' => $user->id]);
        $this->postJson("/api/homes/{$home->id}/notes", ['body' => ''])->assertUnprocessable();
    }

    public function test_referrals_are_logged_in_cents_and_validated(): void
    {
        $home = FuneralHome::factory()->create();
        $user = $this->signIn();

        $this->postJson("/api/homes/{$home->id}/referrals", [
            'referred_on' => '2026-11-01',
            'reference' => 'Family: Alvarez',
            'value_dollars' => 1234.56,
            'notes' => 'Pre-need plan',
        ])
            ->assertCreated()
            ->assertJsonPath('data.value_cents', 123456)
            ->assertJsonPath('data.referred_on', '2026-11-01')
            ->assertJsonPath('data.logged_by', $user->name);

        $this->postJson("/api/homes/{$home->id}/referrals", ['referred_on' => '2999-01-01'])->assertUnprocessable();
        $this->postJson("/api/homes/{$home->id}/referrals", ['referred_on' => '2026-11-01', 'value_dollars' => -5])->assertUnprocessable();
        $this->postJson("/api/homes/{$home->id}/referrals", [])->assertUnprocessable();

        $this->assertSame(1, Referral::query()->count());
    }

    public function test_the_profile_shows_notes_referrals_stage_history_and_assignee(): void
    {
        $home = FuneralHome::factory()->create();
        $agent = User::factory()->create(['name' => 'Dana Agent']);
        $this->signIn();

        $this->putJson("/api/homes/{$home->id}/assignee", ['assigned_to' => $agent->id]);
        $this->putJson("/api/homes/{$home->id}/stage", ['stage' => 'contacted']);
        $this->postJson("/api/homes/{$home->id}/notes", ['body' => 'First call done.']);
        $this->postJson("/api/homes/{$home->id}/referrals", ['referred_on' => '2026-11-01', 'reference' => 'Smith family']);

        $this->getJson("/api/homes/{$home->id}")
            ->assertOk()
            ->assertJsonPath('data.assignee', 'Dana Agent')
            ->assertJsonPath('data.stage', 'contacted')
            ->assertJsonPath('data.notes.0.body', 'First call done.')
            ->assertJsonPath('data.referrals.0.reference', 'Smith family')
            ->assertJsonPath('data.stage_history.0.to_stage', 'contacted');
    }

    public function test_the_list_filters_by_stage_and_assignee(): void
    {
        $me = $this->signIn();
        $other = User::factory()->create();

        $mine = FuneralHome::factory()->create(['assigned_to' => $me->id, 'stage' => Stage::Contacted]);
        $theirs = FuneralHome::factory()->create(['assigned_to' => $other->id, 'stage' => Stage::Found]);
        $nobody = FuneralHome::factory()->create(['assigned_to' => null, 'stage' => Stage::Found]);

        $ids = fn (string $query): array => collect($this->getJson("/api/homes?{$query}")->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids('assigned_to=me'));
        $this->assertSame([$nobody->id], $ids('assigned_to=unassigned'));
        $this->assertSame([$theirs->id], $ids("assigned_to={$other->id}"));
        $this->assertSame([$mine->id], $ids('stage=contacted'));
        $this->assertEqualsCanonicalizing([$theirs->id, $nobody->id], $ids('stage=found'));
        $this->getJson('/api/homes?assigned_to=everyone')->assertUnprocessable();
    }

    public function test_the_dashboard_counts_homes_per_stage_and_referrals(): void
    {
        FuneralHome::factory()->count(2)->create(['stage' => Stage::Found]);
        $meeting = FuneralHome::factory()->create(['stage' => Stage::Meeting]);
        $user = $this->signIn();

        Referral::query()->create(['funeral_home_id' => $meeting->id, 'logged_by' => $user->id, 'referred_on' => '2026-11-01', 'value_cents' => 5000]);
        Referral::query()->create(['funeral_home_id' => $meeting->id, 'logged_by' => $user->id, 'referred_on' => '2026-11-02', 'value_cents' => 2500]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('by_stage.found', 2)
            ->assertJsonPath('by_stage.meeting', 1)
            ->assertJsonPath('referrals.total', 2)
            ->assertJsonPath('referrals.value_cents', 7500);
    }

    public function test_users_can_be_listed_for_assignment(): void
    {
        User::factory()->create(['name' => 'Zed']);
        $this->signIn();

        $this->getJson('/api/users')->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
