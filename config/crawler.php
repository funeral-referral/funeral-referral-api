<?php

use App\Crawler\Adapters\ArizonaAdapter;
use App\Crawler\Adapters\ColoradoAdapter;
use App\Crawler\Adapters\DelawareAdapter;
use App\Crawler\Adapters\OsmAdapter;
use App\Crawler\Adapters\PennsylvaniaAdapter;
use App\Crawler\Adapters\TexasPrepaidAdapter;

return [

    'user_agent' => env('CRAWLER_USER_AGENT', 'FuneralReferralBot/1.0'),

    // The product token robots.txt files address us by.
    'robots_agent' => env('CRAWLER_ROBOTS_AGENT', 'FuneralReferralBot'),

    'delay_ms' => (int) env('CRAWLER_DELAY_MS', 1000),

    'baseline_until' => env('CRAWLER_BASELINE_UNTIL'),

    'adapters' => [
        ColoradoAdapter::class,
        DelawareAdapter::class,
        TexasPrepaidAdapter::class,
        ArizonaAdapter::class,
        PennsylvaniaAdapter::class,
        // The public Overpass hosts disallow /api/ in robots.txt, so OpenStreetMap
        // is opt-in: point OSM_OVERPASS_URL at an endpoint that permits it first.
        ...(env('CRAWLER_OSM_ENABLED', false) ? [OsmAdapter::class] : []),
    ],

    'osm' => [
        'endpoint' => env('OSM_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'states' => ['CO', 'DE', 'TX', 'AZ', 'PA'],
    ],

    'source_priority' => [
        'state_board' => 3,
        'state_registry' => 3,
        'osm' => 2,
        'website' => 1,
    ],

    'closed_status_keywords' => ['surrender', 'revoked', 'closed', 'withdrawn', 'cancel', 'retired'],

];
