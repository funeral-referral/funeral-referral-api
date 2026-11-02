<?php

// Points for the partner score. They add up to 100 for a home that is independent,
// actively licensed, fully contactable, sells pre-need and is confirmed by two sources.
// These weights are judgement calls: change them here and run `php artisan homes:score`.
return [

    'ownership' => [
        'independent' => 30,
        'family_owned' => 30,
        'unknown' => 10,
        'chain' => 0,
    ],

    'licence_active' => 20,
    'licence_listed' => 12,

    'phone' => 10,
    'website' => 5,
    'email' => 5,
    'director' => 5,

    'preneed' => 15,
    'address' => 5,
    'corroborated' => 5,

];
