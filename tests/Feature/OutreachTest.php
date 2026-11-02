<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Enums\OwnershipType;
use App\Enums\Stage;
use App\Mail\OutreachMail;
use App\Models\Campaign;
use App\Models\EmailReply;
use App\Models\FuneralHome;
use App\Models\Suppression;
use App\Models\User;
use App\Outreach\CampaignApprover;
use App\Outreach\OutreachGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutreachTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outreach.postal_address' => '1 Main Street, Denver, CO 80202',
            'outreach.webhook_secret' => 'hook-secret',
            'outreach.daily_send_cap' => 50,
            'services.anthropic.key' => null,
        ]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/campaigns')->assertUnauthorized();
        $this->getJson('/api/replies')->assertUnauthorized();
        $this->postJson('/api/homes/1/campaigns')->assertUnauthorized();
        $this->postJson('/api/campaigns/1/approve')->assertUnauthorized();
        $this->patchJson('/api/campaign-messages/1', [])->assertUnauthorized();
    }

    public function test_generating_a_campaign_drafts_an_email_sequence_and_a_call_script(): void
    {
        $home = $this->home();
        $this->signIn();

        $this->postJson("/api/homes/{$home->id}/campaigns")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.variant', 'independent')
            ->assertJsonPath('data.generated_by', 'template')
            ->assertJsonCount(4, 'data.messages')
            ->assertJsonPath('data.messages.0.type', 'first_email')
            ->assertJsonPath('data.messages.1.type', 'follow_up_1')
            ->assertJsonPath('data.messages.2.type', 'follow_up_2')
            ->assertJsonPath('data.messages.3.type', 'call_script')
            ->assertJsonPath('data.messages.3.subject', null);

        $this->assertSame(4, Campaign::query()->first()->messages()->where('status', 'draft')->count());
    }

    public function test_chain_homes_get_the_chain_variant(): void
    {
        $home = $this->home(['ownership_type' => OwnershipType::Chain, 'chain_name' => 'Dignity Memorial']);
        $this->signIn();

        $this->postJson("/api/homes/{$home->id}/campaigns")
            ->assertCreated()
            ->assertJsonPath('data.variant', 'chain');

        $this->assertStringContainsString(
            'regional or corporate office',
            Campaign::query()->first()->messages()->where('type', 'first_email')->value('body'),
        );
    }

    public function test_claude_writes_the_drafts_when_a_key_is_set(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Here you go: '.json_encode([
            'first_email' => ['subject' => 'Hello from Claude', 'body' => 'First body'],
            'follow_up_1' => ['subject' => 'Follow up', 'body' => 'Second body'],
            'follow_up_2' => ['subject' => 'Last note', 'body' => 'Third body'],
            'call_script' => ['body' => 'Script body'],
        ])]]])]);

        $home = $this->home();
        $this->signIn();

        $this->postJson("/api/homes/{$home->id}/campaigns")
            ->assertCreated()
            ->assertJsonPath('data.generated_by', 'claude')
            ->assertJsonPath('data.messages.0.subject', 'Hello from Claude')
            ->assertJsonPath('data.messages.3.body', 'Script body');

        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-key')
            && str_contains($request['messages'][0]['content'], $home->name));
    }

    public function test_it_falls_back_to_templates_when_claude_fails_or_returns_junk(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->signIn();

        Http::fake(['api.anthropic.com/*' => Http::response('boom', 500)]);
        $first = $this->home();
        $this->postJson("/api/homes/{$first->id}/campaigns")->assertCreated()->assertJsonPath('data.generated_by', 'template');

        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"first_email":{"subject":"x","body":"y"}}']]])]);
        $second = $this->home(['email' => 'two@example.test']);
        $this->postJson("/api/homes/{$second->id}/campaigns")->assertCreated()->assertJsonPath('data.generated_by', 'template');
    }

    public function test_a_home_cannot_have_two_open_campaigns(): void
    {
        $home = $this->home();
        $this->signIn();

        $this->postJson("/api/homes/{$home->id}/campaigns")->assertCreated();
        $this->postJson("/api/homes/{$home->id}/campaigns")->assertUnprocessable()->assertJsonValidationErrors('campaign');
    }

    public function test_drafts_can_be_edited_and_edits_are_flagged_but_approved_text_is_locked(): void
    {
        $home = $this->home();
        $this->signIn();
        $campaign = $this->draft($home);
        $first = $campaign->messages()->where('type', 'first_email')->first();

        $this->patchJson("/api/campaign-messages/{$first->id}", ['subject' => 'My subject', 'body' => 'My own words'])
            ->assertOk()
            ->assertJsonPath('data.edited', true)
            ->assertJsonPath('data.body', 'My own words');

        $this->patchJson("/api/campaign-messages/{$first->id}", ['body' => 'No subject'])->assertUnprocessable();

        $this->postJson("/api/campaigns/{$campaign->id}/approve")->assertOk();

        $this->patchJson("/api/campaign-messages/{$first->id}", ['subject' => 'Sneaky', 'body' => 'Changed after approval'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_approval_is_refused_without_an_email_a_postal_address_or_when_suppressed(): void
    {
        $this->signIn();

        $noEmail = $this->draft($this->home(['email' => null]));
        $this->postJson("/api/campaigns/{$noEmail->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('campaign');

        $suppressedHome = $this->home(['email' => 'optout@example.test']);
        $suppressed = $this->draft($suppressedHome);
        Suppression::add('OptOut@Example.test', 'opted_out');
        $this->postJson("/api/campaigns/{$suppressed->id}/approve")->assertUnprocessable();

        $closed = $this->draft($this->home(['email' => 'closed@example.test', 'closed_at' => now()]));
        $this->postJson("/api/campaigns/{$closed->id}/approve")->assertUnprocessable();

        config(['outreach.postal_address' => null]);
        $ready = $this->draft($this->home(['email' => 'ready@example.test']));
        $this->postJson("/api/campaigns/{$ready->id}/approve")->assertUnprocessable();

        $this->assertSame(0, Campaign::query()->where('status', CampaignStatus::Active)->count());
    }

    public function test_two_campaigns_cannot_email_the_same_address(): void
    {
        $this->signIn();
        $this->approved($this->home(['email' => 'shared@example.test']));

        $other = $this->draft($this->home(['email' => 'Shared@Example.test']));
        $this->postJson("/api/campaigns/{$other->id}/approve")->assertUnprocessable();
    }

    public function test_approval_schedules_the_emails_but_never_the_call_script(): void
    {
        $campaign = $this->approved($this->home());

        $this->assertSame(CampaignStatus::Active, $campaign->status);
        $this->assertNotNull($campaign->approved_at);

        $byType = $campaign->messages->keyBy(fn ($message) => $message->type->value);
        $this->assertSame(MessageStatus::Scheduled, $byType['first_email']->status);
        $this->assertTrue($byType['follow_up_1']->scheduled_for->isSameDay(now()->addDays(4)));
        $this->assertTrue($byType['follow_up_2']->scheduled_for->isSameDay(now()->addDays(10)));
        $this->assertSame(MessageStatus::Ready, $byType['call_script']->status);
        $this->assertNull($byType['call_script']->scheduled_for);
    }

    public function test_due_emails_are_sent_once_with_unsubscribe_and_address_and_the_call_script_never(): void
    {
        Mail::fake();
        $home = $this->home();
        $campaign = $this->approved($home);

        $this->artisan('outreach:send')->assertSuccessful();
        $this->artisan('outreach:send')->assertSuccessful();

        Mail::assertSentCount(1);
        Mail::assertSent(OutreachMail::class, function (OutreachMail $mail): bool {
            $mail->assertSeeInText('1 Main Street, Denver, CO 80202');
            $mail->assertSeeInText('/api/unsubscribe/');

            return $mail->hasTo('info@alder.test')
                && str_contains($mail->headers()->text['List-Unsubscribe'], '/api/unsubscribe/')
                && $mail->headers()->text['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });

        $this->assertSame(Stage::Contacted, $home->refresh()->stage);
        $this->assertSame('info@alder.test', $campaign->messages()->where('type', 'first_email')->value('sent_to'));

        $this->travel(5)->days();
        $this->artisan('outreach:send')->assertSuccessful();
        Mail::assertSentCount(2);

        $this->travel(6)->days();
        $this->artisan('outreach:send')->assertSuccessful();
        Mail::assertSentCount(3);

        $this->assertSame(CampaignStatus::Completed, $campaign->refresh()->status);
        $this->assertSame(MessageStatus::Ready, $campaign->messages()->where('type', 'call_script')->first()->status);
    }

    public function test_an_address_suppressed_after_approval_is_not_emailed(): void
    {
        Mail::fake();
        $campaign = $this->approved($this->home());
        Suppression::add('info@alder.test', 'unsubscribed');

        $this->artisan('outreach:send')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(CampaignStatus::Stopped, $campaign->refresh()->status);
        $this->assertSame('suppressed', $campaign->stopped_reason);
    }

    public function test_the_daily_cap_limits_how_many_emails_go_out(): void
    {
        Mail::fake();
        config(['outreach.daily_send_cap' => 1]);
        $this->approved($this->home(['email' => 'a@example.test']));
        $this->approved($this->home(['email' => 'b@example.test']));

        $this->artisan('outreach:send')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_a_failed_send_stops_the_campaign_instead_of_sending_follow_ups(): void
    {
        $campaign = $this->approved($this->home());
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->artisan('outreach:send')->assertSuccessful();

        $this->assertSame(CampaignStatus::Stopped, $campaign->refresh()->status);
        $this->assertSame('send_failed', $campaign->stopped_reason);
        $this->assertSame(MessageStatus::Failed, $campaign->messages()->where('type', 'first_email')->first()->status);
    }

    public function test_the_unsubscribe_link_suppresses_the_address_and_stops_the_sequence(): void
    {
        Mail::fake();
        $campaign = $this->approved($this->home());
        $this->artisan('outreach:send');

        $url = URL::signedRoute('unsubscribe', ['campaign' => $campaign->id]);

        $this->get($url)->assertOk()->assertSee('You are unsubscribed');

        $this->assertTrue(Suppression::isSuppressed('info@alder.test'));
        $this->assertSame('unsubscribed', $campaign->refresh()->stopped_reason);
        $this->assertSame(
            0,
            $campaign->messages()->whereIn('status', [MessageStatus::Scheduled->value, MessageStatus::Sending->value])->count(),
        );

        $this->travel(11)->days();
        $this->artisan('outreach:send');
        Mail::assertSentCount(1);
    }

    public function test_one_click_unsubscribe_post_works_and_unsigned_links_are_refused(): void
    {
        $campaign = $this->approved($this->home());

        $this->get("/api/unsubscribe/{$campaign->id}")->assertForbidden();
        $this->post("/api/unsubscribe/{$campaign->id}")->assertForbidden();
        $this->assertFalse(Suppression::isSuppressed('info@alder.test'));

        $this->post(URL::signedRoute('unsubscribe', ['campaign' => $campaign->id]), ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertTrue(Suppression::isSuppressed('info@alder.test'));
    }

    public function test_unsubscribing_covers_the_address_that_was_actually_emailed(): void
    {
        Mail::fake();
        $home = $this->home();
        $campaign = $this->approved($home);
        $this->artisan('outreach:send');

        $home->update(['email' => 'new-address@alder.test']);

        $this->get(URL::signedRoute('unsubscribe', ['campaign' => $campaign->id]))->assertOk();

        $this->assertTrue(Suppression::isSuppressed('info@alder.test'));
        $this->assertTrue(Suppression::isSuppressed('new-address@alder.test'));
    }

    public function test_the_inbound_webhook_needs_the_shared_secret(): void
    {
        $payload = ['from' => 'info@alder.test', 'subject' => 'Re: hello', 'text' => 'Sounds good'];

        $this->postJson('/api/webhooks/inbound-email', $payload)->assertForbidden();
        $this->postJson('/api/webhooks/inbound-email', $payload, ['X-Webhook-Secret' => 'wrong'])->assertForbidden();

        config(['outreach.webhook_secret' => null]);
        $this->postJson('/api/webhooks/inbound-email', $payload, ['X-Webhook-Secret' => ''])->assertForbidden();

        $this->assertSame(0, EmailReply::query()->count());
    }

    public function test_a_reply_is_logged_and_stops_the_sequence_and_moves_the_home_to_replied(): void
    {
        Mail::fake();
        $home = $this->home();
        $campaign = $this->approved($home);
        $this->artisan('outreach:send');

        $this->postJson('/api/webhooks/inbound-email', [
            'from' => 'Pat Alder <INFO@alder.test>',
            'subject' => 'Re: Partnering with Alder Funeral Home',
            'text' => 'Sounds interesting, call me Thursday.',
        ], ['X-Webhook-Secret' => 'hook-secret'])->assertCreated();

        $reply = EmailReply::query()->first();
        $this->assertSame('info@alder.test', $reply->from_email);
        $this->assertSame($home->id, $reply->funeral_home_id);
        $this->assertSame($campaign->id, $reply->campaign_id);

        $this->assertSame('replied', $campaign->refresh()->stopped_reason);
        $this->assertSame(Stage::Replied, $home->refresh()->stage);
        $this->assertFalse(Suppression::isSuppressed('info@alder.test'));

        $this->travel(11)->days();
        $this->artisan('outreach:send');
        Mail::assertSentCount(1);
    }

    public function test_a_reply_asking_to_stop_suppresses_the_address(): void
    {
        $home = $this->home();
        $campaign = $this->approved($home);

        $this->postJson('/api/webhooks/inbound-email', [
            'from' => 'info@alder.test',
            'subject' => 'Re: hello',
            'text' => 'Please remove me from your list and do not contact us again.',
        ], ['X-Webhook-Secret' => 'hook-secret'])->assertCreated();

        $this->assertTrue(Suppression::isSuppressed('info@alder.test'));
        $this->assertSame('opted_out', $campaign->refresh()->stopped_reason);
        $this->assertSame(Stage::NotInterested, $home->refresh()->stage);
    }

    public function test_replies_from_unknown_senders_are_still_logged(): void
    {
        $this->postJson('/api/webhooks/inbound-email', [
            'from' => 'stranger@elsewhere.test',
            'text' => 'Who is this?',
        ], ['X-Webhook-Secret' => 'hook-secret'])->assertCreated();

        $this->assertNull(EmailReply::query()->first()->funeral_home_id);

        $this->postJson('/api/webhooks/inbound-email', ['from' => 'no address here'], ['X-Webhook-Secret' => 'hook-secret'])
            ->assertUnprocessable();
    }

    public function test_a_reply_can_be_logged_by_hand(): void
    {
        $home = $this->home();
        $campaign = $this->approved($home);
        $this->signIn();

        $this->postJson("/api/homes/{$home->id}/replies", ['body' => 'They phoned back and want a meeting.'])
            ->assertCreated()
            ->assertJsonPath('data.from_email', 'info@alder.test');

        $this->assertSame('replied', $campaign->refresh()->stopped_reason);

        $noEmail = $this->home(['email' => null]);
        $this->postJson("/api/homes/{$noEmail->id}/replies", ['body' => 'Hello'])->assertUnprocessable();
        $this->postJson("/api/homes/{$noEmail->id}/replies", ['body' => 'Hello', 'from_email' => 'x@example.test'])->assertCreated();
    }

    public function test_only_drafts_can_be_deleted_and_running_campaigns_can_be_stopped(): void
    {
        $this->signIn();
        $draft = $this->draft($this->home(['email' => 'a@example.test']));
        $running = $this->approved($this->home(['email' => 'b@example.test']));

        $this->deleteJson("/api/campaigns/{$draft->id}")->assertNoContent();
        $this->deleteJson("/api/campaigns/{$running->id}")->assertUnprocessable();

        $this->postJson("/api/campaigns/{$running->id}/stop")
            ->assertOk()
            ->assertJsonPath('data.status', 'stopped')
            ->assertJsonPath('data.stopped_reason', 'manual');
        $this->assertSame(0, $running->messages()->where('status', MessageStatus::Scheduled->value)->count());
    }

    public function test_the_home_profile_includes_its_latest_campaign_and_replies(): void
    {
        $home = $this->home();
        $campaign = $this->approved($home);
        $this->signIn();
        $this->postJson("/api/homes/{$home->id}/replies", ['body' => 'Thanks, tell me more.']);

        $this->getJson("/api/homes/{$home->id}")
            ->assertOk()
            ->assertJsonPath('data.campaign.id', $campaign->id)
            ->assertJsonCount(4, 'data.campaign.messages')
            ->assertJsonCount(1, 'data.replies')
            ->assertJsonPath('data.replies.0.body', 'Thanks, tell me more.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function home(array $overrides = []): FuneralHome
    {
        return FuneralHome::factory()->create([
            'name' => 'Alder Funeral Home',
            'city' => 'Denver',
            'state_code' => 'CO',
            'email' => 'info@alder.test',
            'ownership_type' => OwnershipType::Independent,
            'stage' => Stage::Found,
            ...$overrides,
        ]);
    }

    private function draft(FuneralHome $home): Campaign
    {
        return app(OutreachGenerator::class)->generate($home);
    }

    private function approved(FuneralHome $home): Campaign
    {
        return app(CampaignApprover::class)->approve($this->draft($home), User::factory()->create());
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
