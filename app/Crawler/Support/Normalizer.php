<?php

namespace App\Crawler\Support;

final class Normalizer
{
    private const NOISE_WORDS = [
        'funeral', 'home', 'homes', 'service', 'services', 'mortuary', 'mortuaries',
        'chapel', 'chapels', 'inc', 'llc', 'ltd', 'co', 'company', 'corp', 'pc',
        'the', 'and', 'of', 'cremation', 'cremations', 'crematory', 'memorial',
        'gardens', 'family', 'sons', 'son',
    ];

    /**
     * Reduce a business name to its distinctive words so "Smith & Sons Funeral Home, Inc."
     * and "Smith Funeral Service" compare as equal.
     */
    public static function name(string $name): string
    {
        $clean = preg_replace('/[^a-z0-9 ]+/', ' ', strtolower(str_replace('&', ' and ', $name))) ?? '';
        $words = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $distinct = array_values(array_diff($words, self::NOISE_WORDS));

        return implode(' ', $distinct !== [] ? $distinct : $words);
    }

    public static function phone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? '+1'.$digits : null;
    }

    public static function postalCode(?string $raw): ?string
    {
        return preg_match('/\d{5}/', (string) $raw, $matches) === 1 ? $matches[0] : null;
    }

    public static function email(?string $raw): ?string
    {
        $email = strtolower(trim((string) $raw));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    public static function website(?string $raw): ?string
    {
        $url = trim((string) $raw);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        return rtrim($url, '/');
    }

    public static function domain(?string $url): ?string
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    /**
     * Lower-case a street address, drop suite and floor details and spell out
     * common suffixes so "1333 S. Clearview Pkwy, Ste 4" equals "1333 S Clearview Parkway".
     */
    public static function address(?string $raw): ?string
    {
        $address = strtolower(trim((string) $raw));

        if ($address === '') {
            return null;
        }

        $address = preg_split('/\b(suite|ste|unit|floor|fl)\b|#|,/', $address)[0] ?? $address;
        $address = preg_replace('/[^a-z0-9 ]+/', ' ', $address) ?? $address;

        $suffixes = [
            'pkwy' => 'parkway', 'hwy' => 'highway', 'blvd' => 'boulevard', 'st' => 'street',
            'ave' => 'avenue', 'dr' => 'drive', 'rd' => 'road', 'ln' => 'lane', 'ct' => 'court',
        ];

        $words = preg_split('/\s+/', trim($address), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $word): string => $suffixes[$word] ?? $word, $words));
    }

    public static function city(?string $raw): ?string
    {
        $city = trim((string) $raw);

        return $city === '' ? null : ucwords(strtolower($city));
    }
}
