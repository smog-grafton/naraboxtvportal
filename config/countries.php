<?php

// Canonical country discovery collections. Each entry's `match` list is
// compared case-insensitively against both the freeform `country` column and
// the raw text of the `production_countries` JSON column, since production
// data is inconsistent (e.g. "India" vs "Indian", "South Korea" vs "Korea").
// Add entries here to introduce new country-based discovery rails without
// touching controller/model code.

return [
    'india' => [
        'label' => 'India',
        'match' => ['india', 'indian', 'in'],
    ],
    'south-korea' => [
        'label' => 'South Korea',
        'match' => ['south korea', 'korea', 'korean', 'kr', 'rok', 'republic of korea'],
    ],
];
