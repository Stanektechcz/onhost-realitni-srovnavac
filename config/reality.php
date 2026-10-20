<?php

return [
    // Feature flags — everything monetary is OFF by default.
    'features' => [
        'memberships' => (bool) env('REALITY_FEATURE_MEMBERSHIPS', false),
        'payments' => (bool) env('REALITY_FEATURE_PAYMENTS', false),
        'commissions' => (bool) env('REALITY_FEATURE_COMMISSIONS', false),
        'boost' => (bool) env('REALITY_FEATURE_BOOST', false),
        // In-system rental contract (draft, e-signature, auto-close of the listing).
        'contracts' => (bool) env('REALITY_FEATURE_CONTRACTS', false),
    ],

    'listing_lifetime_days' => (int) env('REALITY_LISTING_LIFETIME_DAYS', 60),
    // Sreality-compatible XML-RPC import (/import/RPC2): software_key per CRM vendor, comma separated.
    'import' => [
        'software_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('REALITY_IMPORT_SOFTWARE_KEYS', ''))))),
        'session_minutes' => 15,
    ],
    'per_page' => 12,
    'max_photos' => 20,
    'photo' => ['max_kb' => 8192, 'max_width' => 1600, 'thumb_width' => 480, 'quality' => 80],

    'contracts' => ['code_ttl_minutes' => 10, 'code_attempts' => 5, 'sent_expiry_days' => 30],

    'boost' => ['price_halere' => 19900, 'days' => 7],

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
