<?php

namespace Tests\Feature;

use App\Crawler\Support\PoliteHttp;
use App\Crawler\Support\RobotsDisallowed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PoliteHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['crawler.delay_ms' => 0, 'crawler.robots_agent' => 'FuneralReferralBot']);
        PoliteHttp::forgetState();
    }

    public function test_it_refuses_urls_that_robots_txt_disallows_without_requesting_them(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response("User-agent: *\nDisallow: /private\n"),
            'example.test/*' => Http::response(['ok' => true]),
        ]);

        $this->assertThrows(
            fn () => app(PoliteHttp::class)->get('https://example.test/private/list.json'),
            RobotsDisallowed::class,
        );

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/private'));
    }

    public function test_it_fetches_allowed_urls_with_our_user_agent(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response("User-agent: *\nDisallow: /private\n"),
            'example.test/*' => Http::response(['ok' => true]),
        ]);

        $response = app(PoliteHttp::class)->get('https://example.test/open/list.json', ['page' => 1]);

        $this->assertTrue($response->json('ok'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.test/open/list.json?page=1'
            && $request->hasHeader('User-Agent', config('crawler.user_agent')));
    }

    public function test_robots_rules_apply_to_the_query_string(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response("User-agent: *\nDisallow: /search?*q=\n"),
            'example.test/*' => Http::response([]),
        ]);

        $this->assertThrows(
            fn () => app(PoliteHttp::class)->get('https://example.test/search', ['q' => 'funeral']),
            RobotsDisallowed::class,
        );
    }

    public function test_a_missing_robots_txt_means_no_restrictions(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response('', 404),
            'example.test/*' => Http::response(['ok' => true]),
        ]);

        $this->assertTrue(app(PoliteHttp::class)->get('https://example.test/anything')->json('ok'));
    }

    public function test_an_unreadable_robots_txt_blocks_the_host(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response('', 500),
            'example.test/*' => Http::response(['ok' => true]),
        ]);

        $this->assertThrows(
            fn () => app(PoliteHttp::class)->get('https://example.test/anything'),
            RobotsDisallowed::class,
        );
    }

    public function test_robots_txt_is_fetched_once_per_host(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response(''),
            'example.test/*' => Http::response([]),
        ]);

        app(PoliteHttp::class)->get('https://example.test/a');
        app(PoliteHttp::class)->get('https://example.test/b');

        Http::assertSentCount(3);
    }
}
