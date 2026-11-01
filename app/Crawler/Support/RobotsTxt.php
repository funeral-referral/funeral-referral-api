<?php

namespace App\Crawler\Support;

/**
 * A parsed robots.txt, evaluated the way RFC 9309 describes: the group for our
 * agent wins over the "*" group, the longest matching rule wins, and Allow beats
 * Disallow when both are equally specific.
 */
final class RobotsTxt
{
    /**
     * @param  array<string, array{rules: list<array{allow: bool, pattern: string}>, delay: ?float}>  $groups  Keyed by lower-case user agent.
     */
    private function __construct(
        private readonly array $groups,
        private readonly bool $forbidAll = false,
    ) {}

    public static function parse(string $body): self
    {
        $groups = [];
        $agents = [];
        $readingAgents = false;

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $readingAgents) {
                    $agents = [];
                }

                $agent = strtolower($value);
                $agents[] = $agent;
                $groups[$agent] ??= ['rules' => [], 'delay' => null];
                $readingAgents = true;

                continue;
            }

            $readingAgents = false;

            foreach ($agents as $agent) {
                if (($field === 'allow' || $field === 'disallow') && $value !== '') {
                    $groups[$agent]['rules'][] = ['allow' => $field === 'allow', 'pattern' => $value];
                } elseif ($field === 'crawl-delay' && is_numeric($value)) {
                    $groups[$agent]['delay'] = (float) $value;
                }
            }
        }

        return new self($groups);
    }

    /**
     * No robots.txt was published (a 4xx answer), so nothing is restricted.
     */
    public static function unrestricted(): self
    {
        return new self([]);
    }

    /**
     * The robots.txt could not be read (a 5xx answer or no answer), so the whole host is treated as off limits.
     */
    public static function forbidden(): self
    {
        return new self([], true);
    }

    public function allows(string $pathAndQuery, string $agent): bool
    {
        if ($this->forbidAll) {
            return false;
        }

        $group = $this->groupFor($agent);

        if ($group === null) {
            return true;
        }

        $best = null;

        foreach ($group['rules'] as $rule) {
            if (! self::matches($rule['pattern'], $pathAndQuery)) {
                continue;
            }

            $length = strlen($rule['pattern']);

            if ($best === null || $length > $best['length'] || ($length === $best['length'] && $rule['allow'])) {
                $best = ['length' => $length, 'allow' => $rule['allow']];
            }
        }

        return $best === null || $best['allow'];
    }

    public function crawlDelay(string $agent): ?float
    {
        return $this->groupFor($agent)['delay'] ?? null;
    }

    /**
     * @return array{rules: list<array{allow: bool, pattern: string}>, delay: ?float}|null
     */
    private function groupFor(string $agent): ?array
    {
        return $this->groups[strtolower($agent)] ?? $this->groups['*'] ?? null;
    }

    private static function matches(string $pattern, string $target): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return preg_match($regex, $target) === 1;
    }
}
