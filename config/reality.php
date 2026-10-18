<?php

return [
    // Feature flags — everything monetary is OFF by default.
    'features' => [
        'memberships' => (bool) env('REALITY_FEATURE_MEMBERSHIPS', false),
        'payments' => (bool) env('REALITY_FEATURE_PAYMENTS', false),
        'commissions' => (bool) env('REALITY_FEATURE_COMMISSIONS', false),
    ],

    'listing_lifetime_days' => (int) env('REALITY_LISTING_LIFETIME_DAYS', 60),
    'per_page' => 12,
    'max_photos' => 20,
    'photo' => ['max_kb' => 8192, 'max_width' => 1600, 'thumb_width' => 480, 'quality' => 80],

    'payments' => [
        'provider' => env('REALITY_PAYMENT_PROVIDER', 'stub'), // stub | comgate (not implemented yet)
    ],

    'roles' => ['owner', 'seeker', 'agency', 'developer', 'admin'],
    'offer_types' => ['rent' => 'Pronájem', 'sale' => 'Prodej'],
    'property_types' => ['flat' => 'Byt', 'house' => 'Dům', 'land' => 'Pozemek', 'commercial' => 'Komerční', 'room' => 'Pokoj'],
    'dispositions' => ['1+kk', '1+1', '2+kk', '2+1', '3+kk', '3+1', '4+kk', '4+1', '5+kk', '5+1', '6+', 'atyp'],
    'penb' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'none'],
    'furnishing' => ['none' => 'Nevybaveno', 'partial' => 'Částečně', 'full' => 'Vybaveno'],
    'report_reasons' => [
        'fraud' => 'Podvod / falešný inzerát',
        'illegal' => 'Nezákonný obsah',
        'wrong_info' => 'Nepravdivé údaje',
        'discrimination' => 'Diskriminace',
        'duplicate' => 'Duplicitní inzerát',
        'other' => 'Jiné',
    ],
];
