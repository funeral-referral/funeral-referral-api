<?php

namespace App\Crawler\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PoliteHttp
{
    private static float $lastRequestAt = 0.0;

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $url, array $query = []): Response
    {
        $this->throttle();

        return $this->client()->get($url, $query)->throw();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function postForm(string $url, array $data): Response
    {
        $this->throttle();

        return $this->client()->asForm()->post($url, $data)->throw();
    }

    private function client(): PendingRequest
    {
        return Http::withUserAgent(config('crawler.user_agent'))
            ->acceptJson()
            ->timeout(200)
            ->retry(3, 3000);
    }

    private function throttle(): void
    {
        $delay = (int) config('crawler.delay_ms') / 1000;
        $wait = self::$lastRequestAt + $delay - microtime(true);

        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        self::$lastRequestAt = microtime(true);
    }
}
