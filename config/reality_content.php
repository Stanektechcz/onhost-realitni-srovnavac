<?php

// Static editorial content: guides and contract templates. Contract templates are DRAFTS awaiting legal review.
return [
    'guides' => [
        'pruvodce-najmem' => [
            'title' => 'Průvodce nájmem bytu', 'audience' => 'Hledající', 'minutes' => 6,
            'lead' => 'Od prvního inzerátu po podpis smlouvy: na co se ptát, co zkontrolovat a jaké jsou vaše práva.',
            'sections' => [
                ['Rozpočet', 'Počítejte s nájmem, zálohami na služby (voda, teplo, společné prostory), elektřinou a plynem (často na vás) a internetem. Kauce může podle občanského zákoníku (§ 2254) činit nejvýše trojnásobek měsíčního nájemného.'],
                ['Prohlídka', 'Choďte na prohlídku za denního světla, zkontrolujte vlhkost, okna, tlak vody, stav spotřebičů a hlučnost. Použijte náš checklist prohlídky.'],
                ['Smlouva', 'Ověřte, že pronajímatel je vlastník (výpis z katastru nemovitostí je zdarma na nahlizenidokn.cuzk.cz). Smlouva by měla obsahovat výši nájemného, zálohy, způsob jejich vyúčtování, dobu nájmu, výpovědní podmínky a soupis vybavení.'],
                ['Předání', 'Sepište předávací protokol se stavy měřičů, fotografiemi a seznamem vad. Chrání vás při vracení kauce.'],
                ['Pozor na podvody', 'Nikdy neposílejte peníze předem bez prohlídky a bez ověření vlastnictví. Podezřelý inzerát nám nahlaste.'],
            ],
        ],
        'pruvodce-koupi' => [
            'title' => 'Průvodce koupí nemovitosti', 'audience' => 'Kupující', 'minutes' => 8,
            'lead' => 'Financování, prověrka nemovitosti, rezervace, kupní smlouva a vklad do katastru.',
            'sections' => [
                ['Financování', 'Banky obvykle financují až 80 % hodnoty zástavy (LTV), u mladších žadatelů až 90 %. Spočítejte si orientační splátku v naší hypoteční kalkulačce; konkrétní nabídku vždy posuďte s licencovaným zprostředkovatelem nebo bankou.'],
                ['Prověrka', 'Zkontrolujte list vlastnictví (zástavní práva, věcná břemena, exekuce), stav domu, fond oprav SVJ, PENB a územní plán.'],
                ['Rezervace a záloha', 'Rezervační smlouva by měla jasně uvádět, kdy se záloha vrací. Peníze skládejte do úschovy (advokát, notář, banka).'],
                ['Kupní smlouva a vklad', 'Po podpisu se podává návrh na vklad do katastru. Vlastníkem se stáváte zápisem s účinky ke dni podání návrhu.'],
                ['Další náklady', 'Odhad nemovitosti, poplatek za vklad (2 000 Kč), úschova, pojištění nemovitosti, případné stěhování a rekonstrukce.'],
            ],
        ],
        'checklist-prohlidky' => [
            'title' => 'Checklist prohlídky', 'audience' => 'Hledající i kupující', 'minutes' => 3, 'checklist' => true,
            'lead' => 'Projděte si body na místě. Zaškrtnutí se uloží ve vašem prohlížeči.',
            'items' => ['Vlhkost a plíseň (rohy, za nábytkem, okna)', 'Okna těsní, kování funguje', 'Tlak a teplota vody', 'Elektroinstalace a jističe', 'Topení – typ a stáří kotle / radiátorů', 'Stav spotřebičů', 'Hluk z ulice a od sousedů', 'Signál mobilu a internet', 'Sklep, kolárna, parkování', 'Stav společných prostor', 'Výše záloh a poslední vyúčtování', 'PENB – energetická třída', 'Kdo je vlastník (katastr)', 'Možnost trvalého pobytu', 'Pravidla pro mazlíčky a kouření'],
        ],
        'jak-poznat-podvod' => [
            'title' => 'Jak poznat podvodný inzerát', 'audience' => 'Všichni', 'minutes' => 3,
            'lead' => 'Nejčastější triky podvodníků a jak se jim bránit.',
            'sections' => [
                ['Příliš nízká cena', 'Cena výrazně pod mediánem lokality je varovný signál. U každého inzerátu ukazujeme srovnání s trhem.'],
                ['Platba předem', 'Majitel je „v zahraničí“ a chce kauci před prohlídkou nebo poslání klíčů kurýrem. Nikdy neplaťte bez prohlídky.'],
                ['Tlak na rychlost', '„Zájemců je hodně, pošlete zálohu hned.“ Seriózní pronajímatel vám dá čas.'],
                ['Kontakt mimo portál', 'Snaha přesunout komunikaci na WhatsApp a odkazy na falešné platební brány.'],
                ['Co dělat', 'Inzerát nahlaste tlačítkem „Nahlásit inzerát“. V případě škody kontaktujte Policii ČR (158).'],
            ],
        ],
        'energeticky-stitek' => [
            'title' => 'PENB a náklady na energie', 'audience' => 'Všichni', 'minutes' => 4,
            'lead' => 'Co znamenají třídy A–G a jak z nich odhadnout náklady.',
            'sections' => [
                ['Co je PENB', 'Průkaz energetické náročnosti budovy hodnotí dodanou energii na m² a rok. Majitel jej musí při prodeji a pronájmu doložit (zákon č. 406/2000 Sb.).'],
                ['Třídy', 'A – mimořádně úsporná (pod ~50 kWh/m²/rok), C – úsporná, D – nehospodárná, G – mimořádně nehospodárná (nad ~300 kWh/m²/rok).'],
                ['Odhad nákladů', 'Naše kalkulačka násobí typickou spotřebu třídy plochou bytu a cenou energie. Skutečné náklady závisí na chování, tarifu a orientaci bytu.'],
            ],
        ],
        'predani-bytu' => [
            'title' => 'Předání bytu krok za krokem', 'audience' => 'Pronajímatelé', 'minutes' => 4,
            'lead' => 'Jak předat byt nájemníkovi tak, aby nevznikaly spory.',
            'sections' => [
                ['Před předáním', 'Připravte klíče (počet kusů), návody ke spotřebičům, kontakty na správce a dodavatele energií.'],
                ['Při předání', 'Zapište stavy všech měřičů, pořiďte fotky místností a vad a sepište soupis vybavení. Použijte předávací protokol v účtu.'],
                ['Po předání', 'Nahlaste převod odběrných míst, předejte kontakt na SVJ a domluvte způsob hlášení závad.'],
            ],
        ],
    ],

    'contracts' => [
        'najemni-smlouva-byt' => ['title' => 'Nájemní smlouva k bytu', 'for' => 'rent'],
        'rezervacni-smlouva' => ['title' => 'Rezervační smlouva', 'for' => 'sale'],
        'dohoda-o-ukonceni-najmu' => ['title' => 'Dohoda o ukončení nájmu', 'for' => 'rent'],
    ],

    'handover_rooms' => ['Předsíň', 'Kuchyň', 'Obývací pokoj', 'Ložnice', 'Koupelna', 'WC', 'Balkon / sklep'],
    'handover_meters' => ['electricity' => 'Elektřina (kWh)', 'gas' => 'Plyn (m³)', 'water_cold' => 'Studená voda (m³)', 'water_hot' => 'Teplá voda (m³)', 'heat' => 'Teplo (dílky / GJ)'],
];
