<?php

// Legal/regulatory parameters used by calculators and guides. Every value has a source and the date it was verified.
// Update `verified` whenever you re-check. Nothing here is legal or tax advice.
return [
    'verified' => '2026-10-20',

    'cnb' => [
        'ltv' => 80,              // horní hranice LTV (závazná), vlastní bydlení
        'ltv_under36' => 90,      // žadatel mladší 36 let, vlastní bydlení
        'ltv_investment' => 70,   // doporučení ČNB pro investiční hypotéky od 1. 4. 2026
        'dti_investment' => 7,    // doporučení ČNB pro investiční hypotéky od 1. 4. 2026 (násobek ročního čistého příjmu)
        // DTI a DSTI pro ostatní hypotéky jsou deaktivované (stav potvrzen rozhodnutím BR ČNB 4. 6. 2026).
    ],

    'tax' => [
        'rate' => 15,
        'rate_high' => 23,
        'high_rate_threshold' => 1762812,   // 36× průměrná mzda pro rok 2026
        'flat_expense_pct' => 30,           // § 9 odst. 4 ZDP
        'flat_expense_cap' => 600000,
    ],

    'cadastre' => ['deposit_fee' => 2000],  // pol. 120 sazebníku zákona č. 634/2004 Sb., za každý návrh na vklad

    'lease' => ['deposit_max_months' => 3, 'notice_months' => 3],

    'sources' => [
        'cnb' => ['ČNB – tisková zpráva: ČNB doporučuje přísnější limity pro investiční hypotéky (LTV 70 %, DTI 7 od 1. 4. 2026; LTV 80 %/90 % do 36 let; DTI a DSTI deaktivované)', 'https://www.cnb.cz/cs/cnb-news/tiskove-zpravy/CNB-doporucuje-prisnejsi-limity-pro-investicni-hypoteky.-Kapitalove-rezervy-se-nemeni/'],
        'cnb_june' => ['ČNB – rozhodnutí bankovní rady v oblasti finanční stability (4. 6. 2026, limity beze změny)', 'https://www.cnb.cz/cs/financni-stabilita/rozhodnuti-bankovni-rady-cnb/Rozhodnuti-bankovni-rady-CNB-v-oblasti-financni-stability-1780578000000/?tab=record'],
        'zdp' => ['Zákon č. 586/1992 Sb., o daních z příjmů – § 4 odst. 1 písm. a), b), § 9 odst. 4 (znění od 1. 8. 2026)', 'https://www.zakonyprolidi.cz/cs/1992-586'],
        'oz' => ['Zákon č. 89/2012 Sb., občanský zákoník – § 2231, § 2254, § 2286, § 2288, § 2291 (znění od 1. 1. 2026)', 'https://www.zakonyprolidi.cz/cs/2012-89'],
        'vklad' => ['ČÚZK – Návrh na zahájení řízení o povolení vkladu (správní poplatek 2 000 Kč za návrh)', 'https://cuzk.gov.cz/Je-dobre-vedet/Zivotni-situace/Vybrane-zivotni-situace-katastr-nemovitosti/Navrh-na-zahajeni-rizeni-o-povoleni-vkladu-do-kata.aspx'],
        'penb' => ['Vyhláška č. 264/2020 Sb., o energetické náročnosti budov (klasifikační třídy A–G)', 'https://www.zakonyprolidi.cz/cs/2020-264'],
        'nabyti' => ['Zákon č. 386/2020 Sb. – zrušení daně z nabytí nemovitých věcí', 'https://www.zakonyprolidi.cz/cs/2020-386'],
    ],
];
