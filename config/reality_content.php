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
        // Interactive bureaucracy checklists (progress in localStorage). Items: [text, [[label, official url], ...]]. Verified 2026-10-20.
        'pronajimam-byt' => [
            'title' => 'Pronajímám byt – checklist', 'audience' => 'Pronajímatelé', 'minutes' => 6, 'checklist' => true,
            'lead' => 'Krok za krokem od přípravy bytu po daňové přiznání. Postup se ukládá ve vašem prohlížeči.',
            'items' => [
                ['Ověřte list vlastnictví (zástavy, věcná břemena, souhlas spoluvlastníků/SVJ podle stanov)', [['ČÚZK – Nahlížení do KN', 'https://nahlizenidokn.cuzk.gov.cz/']]],
                ['Zajistěte průkaz energetické náročnosti (PENB) – třídu je nutné uvádět v inzerátu', [['Vyhláška č. 264/2020 Sb.', 'https://www.zakonyprolidi.cz/cs/2020-264']]],
                ['Stanovte nájemné a kauci – jistota a smluvní pokuty dohromady max. 3× měsíční nájemné (§ 2254 OZ)', [['Kalkulačka kauce', '/kalkulacky?tab=kauce'], ['Občanský zákoník', 'https://www.zakonyprolidi.cz/cs/2012-89']]],
                ['Ověřte identitu zájemce a neberte peníze před podpisem smlouvy', []],
                ['Písemná nájemní smlouva: doba nájmu, nájemné, zálohy na služby a jejich vyúčtování, počet osob, zvířata', [['Vzor smlouvy (po přihlášení)', '/moje/smlouvy']]],
                ['Předávací protokol se stavy měřidel, fotkami a soupisem vybavení', []],
                ['Přepište nebo dohodněte odběr elektřiny a plynu (smlouva s dodavatelem)', [['ERÚ – práva spotřebitele', 'https://eru.gov.cz/']]],
                ['Pojištění nemovitosti a odpovědnosti pronajímatele; doporučte nájemci pojištění domácnosti', []],
                ['Souhlas s trvalým pobytem nájemce (podpis vlastníka nebo smlouva jako doklad)', [['Portál veřejné správy – trvalý pobyt', 'https://portal.gov.cz/sluzby-vs/ohlaseni-zmeny-mista-trvaleho-pobytu-S605']]],
                ['Oznamte SVJ / správci změnu počtu osob (rozúčtování služeb)', []],
                ['Roční vyúčtování služeb nájemci nejpozději do 4 měsíců po skončení zúčtovacího období', [['Zákon č. 67/2013 Sb.', 'https://www.zakonyprolidi.cz/cs/2013-67']]],
                ['Daňové přiznání k dani z příjmů – příjmy z nájmu (§ 9 ZDP), paušál 30 % nebo skutečné výdaje', [['Finanční správa', 'https://www.financnisprava.gov.cz/'], ['Online finanční úřad (EPO)', 'https://adisspr.mfcr.cz/pmd/home'], ['Kalkulačka cash-flow', '/kalkulacky?tab=cashflow']]],
                ['Daň z nemovitých věcí – přiznání do 31. ledna, pokud jste byt nabyli v minulém roce', [['Finanční správa', 'https://www.financnisprava.gov.cz/']]],
            ],
        ],
        'stehuji-se-do-najmu' => [
            'title' => 'Stěhuji se do nájmu – checklist', 'audience' => 'Nájemníci', 'minutes' => 5, 'checklist' => true,
            'lead' => 'Co zkontrolovat a vyřídit před podpisem, při předání i po nastěhování.',
            'items' => [
                ['Ověřte, že pronajímatel je vlastník (list vlastnictví zdarma)', [['ČÚZK – Nahlížení do KN', 'https://nahlizenidokn.cuzk.gov.cz/']]],
                ['Zkontrolujte, zda vlastník není v insolvenci', [['Insolvenční rejstřík', 'https://isir.justice.cz/']]],
                ['Kauce max. 3× nájemné; platba až po podpisu smlouvy a nejlépe na účet', [['Kalkulačka kauce a výpovědi', '/kalkulacky?tab=kauce']]],
                ['Přečtěte si smlouvu: výše záloh, vyúčtování, výpovědní podmínky, zvířata, podnájem', [['Občanský zákoník § 2235 a násl.', 'https://www.zakonyprolidi.cz/cs/2012-89']]],
                ['Předávací protokol: stavy měřidel, fotky závad, klíče', []],
                ['Přepis nebo nová smlouva na elektřinu / plyn, internet', [['ERÚ – změna dodavatele', 'https://eru.gov.cz/']]],
                ['Pojištění domácnosti a odpovědnosti nájemce', []],
                ['Ohlášení změny trvalého pobytu (se souhlasem vlastníka, poplatek 50 Kč)', [['Portál veřejné správy', 'https://portal.gov.cz/sluzby-vs/ohlaseni-zmeny-mista-trvaleho-pobytu-S605'], ['Portál občana', 'https://obcan.portal.gov.cz/']]],
                ['Změna adresy u banky, zaměstnavatele, pojišťovny, v registru vozidel', [['Portál občana', 'https://obcan.portal.gov.cz/']]],
                ['Přihlášení k poplatku za komunální odpad podle vyhlášky obce', []],
                ['Uschovejte si kopii smlouvy, protokolu a potvrzení o kauci', []],
            ],
        ],
        'prodavam' => [
            'title' => 'Prodávám nemovitost – checklist', 'audience' => 'Prodávající', 'minutes' => 6, 'checklist' => true,
            'lead' => 'Od přípravy dokumentů přes kupní smlouvu a vklad až po daňové přiznání.',
            'items' => [
                ['Stáhněte si list vlastnictví a zkontrolujte zápisy (zástavy, břemena, plomby)', [['ČÚZK – Nahlížení do KN', 'https://nahlizenidokn.cuzk.gov.cz/']]],
                ['Zajistěte PENB – třída musí být v inzerátu a průkaz se předává kupujícímu', [['Vyhláška č. 264/2020 Sb.', 'https://www.zakonyprolidi.cz/cs/2020-264']]],
                ['Připravte prohlášení vlastníka / stanovy SVJ, potvrzení o bezdlužnosti vůči SVJ', []],
                ['Domluvte s bankou vyplacení hypotéky a výmaz zástavního práva', []],
                ['Rezervační a kupní smlouva, úschova kupní ceny (advokát, notář, banka)', [['Náklady prodeje', '/kalkulacky?tab=prodej']]],
                ['Podání návrhu na vklad do katastru (2 000 Kč za návrh), ochranná lhůta 20 dní', [['ČÚZK – návrh na vklad', 'https://cuzk.gov.cz/Je-dobre-vedet/Zivotni-situace/Vybrane-zivotni-situace-katastr-nemovitosti/Navrh-na-zahajeni-rizeni-o-povoleni-vkladu-do-kata.aspx']]],
                ['Předávací protokol, stavy měřidel, odhlášení energií a změna plátce u SVJ', [['ERÚ', 'https://eru.gov.cz/']]],
                ['Zrušte pojištění nemovitosti k datu předání', []],
                ['Odhlaste trvalý pobyt / ohlaste nové místo pobytu', [['Portál veřejné správy', 'https://portal.gov.cz/sluzby-vs/ohlaseni-zmeny-mista-trvaleho-pobytu-S605']]],
                ['Daň z příjmu: ověřte časový test (2 roky bydliště / 10 let, resp. 5 let u nabytí před 2021); jinak přiznání do 1. dubna (elektronicky do 1. května)', [['Kalkulačka daně z prodeje', '/kalkulacky?tab=prodej'], ['Online finanční úřad (EPO)', 'https://adisspr.mfcr.cz/pmd/home']]],
                ['Osvobozený příjem nad 5 mil. Kč oznamte finančnímu úřadu', [['Finanční správa', 'https://www.financnisprava.gov.cz/']]],
            ],
        ],
        'kupuji' => [
            'title' => 'Kupuji nemovitost – checklist', 'audience' => 'Kupující', 'minutes' => 7, 'checklist' => true,
            'lead' => 'Financování, prověrka, smlouvy, katastr, energie, pobyt, daně a pojištění.',
            'items' => [
                ['Spočítejte si hypotéku a limity ČNB (LTV 80 %, do 36 let 90 %)', [['Hypoteční kalkulačka', '/kalkulacky?tab=hypoteka'], ['ČNB', 'https://www.cnb.cz/cs/cnb-news/tiskove-zpravy/CNB-doporucuje-prisnejsi-limity-pro-investicni-hypoteky.-Kapitalove-rezervy-se-nemeni/']]],
                ['Započítejte vedlejší náklady koupě', [['Náklady koupě', '/kalkulacky?tab=koupe']]],
                ['Prověřte list vlastnictví, zástavy, věcná břemena a plomby', [['ČÚZK – Nahlížení do KN', 'https://nahlizenidokn.cuzk.gov.cz/']]],
                ['Prověřte prodávajícího v insolvenčním rejstříku', [['Insolvenční rejstřík', 'https://isir.justice.cz/']]],
                ['Zkontrolujte povodňové a hlukové mapy lokality', [['Mapy povodňového nebezpečí (MŽP)', 'https://cds.mzp.cz/'], ['Strategické hlukové mapy (MZd)', 'https://geoportal.mzcr.cz/shm/']]],
                ['Vyžádejte si PENB, stanovy a hospodaření SVJ, výši fondu oprav', []],
                ['Rezervační smlouva s jasnými podmínkami vrácení zálohy', []],
                ['Kupní smlouva + úschova kupní ceny (advokát, notář, banka)', []],
                ['Návrh na vklad vlastnického práva (a zástavního práva při hypotéce), 2 000 Kč za návrh', [['ČÚZK – návrh na vklad', 'https://cuzk.gov.cz/Je-dobre-vedet/Zivotni-situace/Vybrane-zivotni-situace-katastr-nemovitosti/Navrh-na-zahajeni-rizeni-o-povoleni-vkladu-do-kata.aspx']]],
                ['Sledujte stav řízení a po zápisu si stáhněte nový list vlastnictví', [['ČÚZK – Nahlížení do KN', 'https://nahlizenidokn.cuzk.gov.cz/']]],
                ['Pojištění nemovitosti (banka ho u hypotéky vyžaduje) a domácnosti', []],
                ['Přepis elektřiny a plynu, nahlášení do SVJ, předávací protokol se stavy měřidel', [['ERÚ', 'https://eru.gov.cz/']]],
                ['Ohlášení změny trvalého pobytu', [['Portál veřejné správy', 'https://portal.gov.cz/sluzby-vs/ohlaseni-zmeny-mista-trvaleho-pobytu-S605'], ['Portál občana', 'https://obcan.portal.gov.cz/']]],
                ['Daň z nemovitých věcí – přiznání do 31. ledna následujícího roku (daň z nabytí už neexistuje)', [['Finanční správa', 'https://www.financnisprava.gov.cz/'], ['Online finanční úřad (EPO)', 'https://adisspr.mfcr.cz/pmd/home']]],
                ['Odpočet úroků z hypotéky na vlastní bydlení v daňovém přiznání / ročním zúčtování (§ 15 ZDP)', [['Zákon o daních z příjmů', 'https://www.zakonyprolidi.cz/cs/1992-586']]],
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
