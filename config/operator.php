<?php

/*
 * Provozovatel (právní subjekt) projektu – kontrakt onhost-operator/1 (mapa ekosystému, 2026-11-07).
 * Registr subjektů + ukazatel na aktivního provozovatele. Přepnutí je jen změna konfigurace:
 *   ECOSYSTEM_OPERATOR=<id|slug>               aktivní provozovatel (výchozí osvc-stanek-08094616; alias adrian-stanek)
 *   ECOSYSTEM_OPERATOR_VAT_MODE=<režim>        non_payer | identified_person | payer | group pro aktivní subjekt
 *                                              (výchozí non_payer; vat_payer se odvozuje: payer|group)
 *   ECOSYSTEM_OPERATOR_<SLUG>_<POLE>=<hodnota>  přepsání pole (např. ECOSYSTEM_OPERATOR_ADRIAN_STANEK_VAT_PAYER=true,
 *                                               ADDRESS_CITY, BANK_IBAN …)
 *   ECOSYSTEM_OPERATOR_<SLUG>_SHOW_ADDRESS_PUBLIC=true|false  ulice sídla na veřejném webu (patička, pravidla, kontakt,
 *                                               JSON-LD); výchozí false u OSVČ (bydliště), true u firem. Doklady ji mají vždy.
 * Schéma: program-2026-10/12-PRAVNI-SUBJEKTY-a-konfigurace.md §6.1. Nový subjekt (dceřiná firma) se přidá sem do 'operators'. Žádná tajemství (hesla, API klíče) sem nepatří.
 * Doklady (faktury, objednávky, souhlasy s VOP) ukládají snapshot – změna platí jen pro nové doklady.
 */
$operators = [
    // klíč = slug (alias), id = identifikátor subjektu ukládaný do snapshotů
    'adrian-stanek' => [
        'id' => 'osvc-stanek-08094616',
        'name' => 'Adrian Staněk',
        'legal_form' => 'osvc', // osvc | sro | as | zs | other
        'ico' => '08094616',
        'dic' => '', // prázdné = nemá DIČ
        'vat_mode' => 'non_payer', // non_payer | identified_person | payer | group
        'vat_payer' => false, // odvozeno z vat_mode (payer|group)
        'vat_id' => '',
        'address' => ['street' => 'Molákova 2145/5', 'city' => 'Brno', 'zip' => '628 00', 'country' => 'CZ'],
        'show_address_public' => false, // OSVČ: sídlo = bydliště, na webu jen obec; doklady vždy plnou adresu
        'registry' => 'fyzická osoba podnikající na základě živnostenského oprávnění, zapsaná v živnostenském rejstříku',
        'registry_note' => 'Fyzická osoba zapsaná v živnostenském rejstříku.',
        'privacy_url' => '',
        'valid_from' => '2020-01-01T00:00:00+01:00',
        'email' => '',
        'phone' => '',
        'bank' => ['iban' => '', 'bic' => '', 'account' => ''],
        'footer' => 'Provozovatel: Adrian Staněk, IČO 08094616, neplátce DPH',
        'number_prefix' => '', // prefix číselné řady dokladů; prázdné = odvozeno ze slugu (App\Support\DocumentNumber)
    ],
    // Historie (validFrom): místo jednoho záznamu smí slug nést seznam verzí, aktivní je ta s nejvyšším valid_from <= teď;
    // nic se nemaže, např. 'adrian-stanek' => [[...'valid_from' => '2020-01-01T00:00:00+01:00'], [...'valid_from' => '2027-01-01T00:00:00+01:00']].
];

$active = (string) env('ECOSYSTEM_OPERATOR', 'osvc-stanek-08094616');
$applyEnv = static function (string $slug, array $op) use ($active): array {
    $out = $op;
    $out['show_address_public'] ??= ($op['legal_form'] ?? 'other') !== 'osvc';
    $prefix = 'ECOSYSTEM_OPERATOR_'.strtoupper(str_replace('-', '_', $slug)).'_';
    $vatPayerOverride = env($prefix.'VAT_PAYER');
    foreach ($out as $field => $value) {
        if (is_array($value)) {
            foreach (array_keys($value) as $sub) {
                $env = env($prefix.strtoupper($field.'_'.$sub));
                if ($env !== null) {
                    $out[$field][$sub] = (string) $env;
                }
            }

            continue;
        }
        $env = env($prefix.strtoupper($field));
        if ($env === null) {
            continue;
        }
        $out[$field] = is_bool($value)
            ? (is_bool($env) ? $env : filter_var($env, FILTER_VALIDATE_BOOL))
            : (string) $env;
    }
    // ECOSYSTEM_OPERATOR_VAT_MODE platí pro aktivní subjekt; starší přepis <SLUG>_VAT_PAYER=true znamená payer.
    if (in_array($active, [$slug, $op['id'] ?? null], true) && env('ECOSYSTEM_OPERATOR_VAT_MODE') !== null) {
        $out['vat_mode'] = (string) env('ECOSYSTEM_OPERATOR_VAT_MODE');
    } elseif ($vatPayerOverride !== null && ($out['vat_mode'] ?? 'non_payer') === 'non_payer') {
        $out['vat_mode'] = $out['vat_payer'] ? 'payer' : 'non_payer';
    }
    $out['vat_payer'] = in_array($out['vat_mode'] ?? 'non_payer', ['payer', 'group'], true);

    return $out;
};
foreach ($operators as $slug => $entry) {
    $operators[$slug] = array_is_list($entry)
        ? array_map(static fn (array $v): array => $applyEnv($slug, $v), $entry)
        : $applyEnv($slug, $entry);
}

return [
    'format' => 'onhost-operator/1',
    'operator' => $active,
    'default' => 'adrian-stanek',
    'operators' => $operators,
];
