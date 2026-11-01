<?php

namespace App\Crawler\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class PoliteHttp
{
    private static float $lastRequestAt = 0.0;

    /** @var array<string, RobotsTxt> */
    private static array $robots = [];

    public static function forgetState(): void
    {
        self::$robots = [];
        self::$lastRequestAt = 0.0;
    }

    /**
     * @param  array<string, mixed>  $query
     *
     * @throws RobotsDisallowed When the host's robots.txt forbids this URL for our agent.
     */
    public function get(string $url, array $query = []): Response
    {
        $this->throttle($this->guard($url, $query));

        return $this->client()->get($url, $query)->throw();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RobotsDisallowed When the host's robots.txt forbids this URL for our agent.
     */
    public function postForm(string $url, array $data): Response
    {
        $this->throttle($this->guard($url));

        return $this->client()->asForm()->post($url, $data)->throw();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return float The crawl delay the host asks for, in seconds.
     */
    private function guard(string $url, array $query = []): float
    {
        $parts = parse_url($url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        $robots = self::$robots[$origin] ??= $this->fetchRobots($origin);
        $agent = (string) config('crawler.robots_agent');

        $queryString = $query !== [] ? http_build_query($query) : ($parts['query'] ?? '');
        $target = ($parts['path'] ?? '/').($queryString !== '' ? '?'.$queryString : '');

        if (! $robots->allows($target, $agent)) {
            throw new RobotsDisallowed("robots.txt at {$origin} does not allow {$agent} to fetch {$target}");
        }

        return $robots->crawlDelay($agent) ?? 0.0;
    }

    private function fetchRobots(string $origin): RobotsTxt
    {
        $this->throttle(0.0);

        try {
            $response = Http::withUserAgent(config('crawler.user_agent'))
                ->timeout(30)
                ->get($origin.'/robots.txt');
        } catch (Throwable) {
            return RobotsTxt::forbidden();
        }

        return match (true) {
            $response->successful() => RobotsTxt::parse($response->body()),
            $response->clientError() => RobotsTxt::unrestricted(),
            default => RobotsTxt::forbidden(),
        };
    }

    private function client(): PendingRequest
    {
        return Http::withUserAgent(config('crawler.user_agent'))
            ->acceptJson()
            ->timeout(200)
            ->retry(3, 3000);
    }

    private function throttle(float $hostDelay): void
    {
        $delay = max((int) config('crawler.delay_ms') / 1000, $hostDelay);
        $wait = self::$lastRequestAt + $delay - microtime(true);

        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        self::$lastRequestAt = microtime(true);
    }
}
