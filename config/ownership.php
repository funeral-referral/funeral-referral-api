<?php

return [

    // Brands of large consolidators, as regular expression fragments. They are matched
    // against a home's name, owner and website domain. The list is not exhaustive:
    // local brands bought by a consolidator only show up through the location rule below.
    'chains' => [
        'SCI / Dignity Memorial' => [
            'dignity memorial', 'dignitymemorial', 'service corporation international', '\bsci\b',
            'neptune society', 'national cremation society', 'funeraria del angel',
            'funerariadelangel', 'funeriasdelangel', 'funerariasdelangel', 'alderwoods',
        ],
        'Foundation Partners Group' => ['\bfpg\b', 'foundation partners'],
        'Carriage Services' => ['carriage services', 'carriage funeral'],
        'Park Lawn Corporation' => ['park lawn'],
        'StoneMor' => ['stonemor'],
    ],

    // An operator with at least this many open locations in the data counts as a chain.
    'chain_location_threshold' => 8,

    'family_pattern' => '\bfamily\b|& sons?\b|\band sons?\b|\bbrothers\b|\bbros\b',

    // An owner that matches this is a business, not a person.
    'corporate_owner_pattern' => '\b(inc|llc|l\.l\.c|llp|lllp|corp|corporation|company|co|ltd|society|diocese|trust|group|partners|associates|cemeteries|cemetery|mortuaries|mortuary|funeral|chapel|cremation|services|memorial)\b',

    // Domains many unrelated businesses use, so sharing one says nothing about ownership.
    'generic_domains' => [
        'facebook.com', 'gmail.com', 'yahoo.com', 'hotmail.com', 'aol.com', 'outlook.com',
        'wixsite.com', 'weebly.com', 'sites.google.com', 'squarespace.com',
    ],

];
