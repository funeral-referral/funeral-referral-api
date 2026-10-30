<?php

use App\Crawler\Adapters\ColoradoAdapter;
use App\Crawler\Adapters\DelawareAdapter;
use App\Crawler\Adapters\OsmAdapter;
use App\Crawler\Adapters\TexasPrepaidAdapter;

return [

    'user_agent' => env('CRAWLER_USER_AGENT', 'FuneralReferralBot/1.0'),

    'delay_ms' => (int) env('CRAWLER_DELAY_MS', 1000),

    'adapters' => [
        ColoradoAdapter::class,
        DelawareAdapter::class,
        TexasPrepaidAdapter::class,
        OsmAdapter::class,
    ],

    'osm' => [
        'endpoint' => env('OSM_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'states' => ['CO', 'DE', 'TX'],
    ],

    'source_priority' => [
        'state_board' => 3,
        'osm' => 2,
        'website' => 1,
    ],

    'closed_status_keywords' => ['surrender', 'revoked', 'closed', 'withdrawn', 'cancel', 'retired'],

];
