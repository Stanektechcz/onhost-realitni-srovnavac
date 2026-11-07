<?php

/*
 * Provozovatel (právní subjekt) projektu – kontrakt onhost-operator/1 (mapa ekosystému, 2026-11-07).
 * Registr subjektů + ukazatel na aktivního provozovatele. Přepnutí je jen změna konfigurace:
 *   ECOSYSTEM_OPERATOR=<slug>                  aktivní provozovatel (výchozí adrian-stanek)
 *   ECOSYSTEM_OPERATOR_<SLUG>_<POLE>=<hodnota>  přepsání pole (např. ECOSYSTEM_OPERATOR_ADRIAN_STANEK_VAT_PAYER=true,
 *                                               ADDRESS_CITY, BANK_IBAN …)
 * Nový subjekt (dceřiná firma) se přidá sem do 'operators'. Žádná tajemství (hesla, API klíče) sem nepatří.
 * Doklady (faktury, objednávky, souhlasy s VOP) ukládají snapshot – změna platí jen pro nové doklady.
 */
$operators = [
    'adrian-stanek' => [
        'name' => 'Adrian Staněk',
        'legal_form' => 'osvc', // osvc | sro | as | zs | other
        'ico' => '08094616',
        'dic' => '', // prázdné = nemá DIČ
        'vat_payer' => false,
        'vat_id' => '',
        'address' => ['street' => 'Molákova 2145/5', 'city' => 'Brno', 'zip' => '628 00', 'country' => 'CZ'],
        'registry' => 'fyzická osoba podnikající na základě živnostenského oprávnění, zapsaná v živnostenském rejstříku',
        'email' => '',
        'phone' => '',
        'bank' => ['iban' => '', 'bic' => '', 'account' => ''],
        'footer' => 'Provozovatel: Adrian Staněk, IČO 08094616, neplátce DPH',
    ],
];

foreach ($operators as $slug => $op) {
    $prefix = 'ECOSYSTEM_OPERATOR_'.strtoupper(str_replace('-', '_', $slug)).'_';
    foreach ($op as $field => $value) {
        if (is_array($value)) {
            foreach (array_keys($value) as $sub) {
                $env = env($prefix.strtoupper($field.'_'.$sub));
                if ($env !== null) {
                    $operators[$slug][$field][$sub] = (string) $env;
                }
            }

            continue;
        }
        $env = env($prefix.strtoupper($field));
        if ($env === null) {
            continue;
        }
        $operators[$slug][$field] = is_bool($value)
            ? (is_bool($env) ? $env : filter_var($env, FILTER_VALIDATE_BOOL))
            : (string) $env;
    }
}

return [
    'format' => 'onhost-operator/1',
    'operator' => (string) env('ECOSYSTEM_OPERATOR', 'adrian-stanek'),
    'default' => 'adrian-stanek',
    'operators' => $operators,
];
