<?php

namespace Tests\Unit;

use App\Crawler\Support\RobotsTxt;
use PHPUnit\Framework\TestCase;

class RobotsTxtTest extends TestCase
{
    private const AGENT = 'FuneralReferralBot';

    public function test_a_blanket_disallow_blocks_every_path(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /\n");

        $this->assertFalse($robots->allows('/api/interpreter', self::AGENT));
    }

    public function test_a_group_for_our_agent_overrides_the_wildcard_group(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /\n\nUser-agent: FuneralReferralBot\nAllow: /\n");

        $this->assertTrue($robots->allows('/anything', self::AGENT));
        $this->assertFalse($robots->allows('/anything', 'SomeOtherBot'));
    }

    public function test_the_longest_matching_rule_wins_and_allow_wins_a_tie(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /data/\nAllow: /data/public/\nDisallow: /tie\nAllow: /tie\n");

        $this->assertFalse($robots->allows('/data/secret.json', self::AGENT));
        $this->assertTrue($robots->allows('/data/public/list.json', self::AGENT));
        $this->assertTrue($robots->allows('/tie', self::AGENT));
    }

    public function test_wildcards_and_end_anchors_are_supported(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /browse?*&q=\nDisallow: /*.pdf\$\n");

        $this->assertFalse($robots->allows('/browse?page=2&q=funeral', self::AGENT));
        $this->assertTrue($robots->allows('/browse?page=2', self::AGENT));
        $this->assertFalse($robots->allows('/files/list.pdf', self::AGENT));
        $this->assertTrue($robots->allows('/files/list.pdf.html', self::AGENT));
    }

    public function test_comments_blank_lines_and_crawl_delay_are_handled(): void
    {
        $robots = RobotsTxt::parse("# welcome\nSitemap: https://example.test/sitemap.xml\n\nUser-agent: * # everyone\nCrawl-delay: 2\n\nDisallow: /private # keep out\n");

        $this->assertSame(2.0, $robots->crawlDelay(self::AGENT));
        $this->assertFalse($robots->allows('/private/page', self::AGENT));
        $this->assertTrue($robots->allows('/public', self::AGENT));
    }

    public function test_an_empty_disallow_allows_everything(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow:\n");

        $this->assertTrue($robots->allows('/anything', self::AGENT));
    }

    public function test_unrestricted_and_forbidden_hosts(): void
    {
        $this->assertTrue(RobotsTxt::unrestricted()->allows('/a', self::AGENT));
        $this->assertFalse(RobotsTxt::forbidden()->allows('/a', self::AGENT));
    }
}
