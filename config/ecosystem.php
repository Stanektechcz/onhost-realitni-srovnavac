<?php

/*
 * Napojení na ekosystém ONhost (P1 katalog, P3 sběrnice, P7 zdraví). Vše VYPNUTO ve výchozím stavu:
 * s vypnutými příznaky aplikace běží zcela samostatně, žádná trasa ani HTTP volání neexistuje.
 */
return [
    'project' => 'reality',

    'catalog' => [
        'enabled' => (bool) env('ECOSYSTEM_CATALOG_ENABLED', false),
        'max_offers' => 50,
    ],

    'health' => [
        'enabled' => (bool) env('ECOSYSTEM_HEALTH_ENABLED', false),
        'db_connection' => null, // výchozí připojení
    ],

    'bus' => [
        'enabled' => (bool) env('ECOSYSTEM_BUS_ENABLED', false),
        // aktuální tajemství první, předchozí po dobu rotace (oddělené čárkou)
        'secrets' => array_values(array_filter(array_map('trim', explode(',', (string) env('ECOSYSTEM_BUS_SECRETS', ''))))),
        'tolerance' => (int) env('ECOSYSTEM_BUS_TOLERANCE', 300),
        // odběratel odchozích událostí (jen https)
        'subscriber_url' => env('ECOSYSTEM_BUS_SUBSCRIBER_URL'),
        'timeout' => (int) env('ECOSYSTEM_BUS_TIMEOUT', 5),
    ],
];
