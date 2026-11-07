<?php

/*
 * Static locality tree for SEO landing pages (/lokality/{slug}, /pronajem/{slug}, /prodej/{slug}).
 * Kraje match the area names used by ČSÚ (market_benchmarks.area). Cities are the krajská and
 * okresní města used to match listings (listings.city). Prague districts match listings.district/city.
 */
return [
    'kraje' => [
        'praha' => ['name' => 'Hlavní město Praha', 'place' => 'Praha', 'cities' => ['Praha'], 'neighbours' => ['stredocesky-kraj']],
        'stredocesky-kraj' => ['name' => 'Středočeský kraj', 'cities' => ['Kladno', 'Mladá Boleslav', 'Příbram', 'Kolín', 'Benešov', 'Beroun', 'Kutná Hora', 'Mělník', 'Nymburk', 'Rakovník', 'Brandýs nad Labem-Stará Boleslav', 'Černošice'], 'neighbours' => ['praha', 'jihocesky-kraj', 'plzensky-kraj', 'ustecky-kraj', 'liberecky-kraj', 'kralovehradecky-kraj', 'pardubicky-kraj', 'kraj-vysocina']],
        'jihocesky-kraj' => ['name' => 'Jihočeský kraj', 'cities' => ['České Budějovice', 'Tábor', 'Písek', 'Strakonice', 'Jindřichův Hradec', 'Český Krumlov', 'Prachatice'], 'neighbours' => ['stredocesky-kraj', 'plzensky-kraj', 'kraj-vysocina', 'jihomoravsky-kraj']],
        'plzensky-kraj' => ['name' => 'Plzeňský kraj', 'cities' => ['Plzeň', 'Klatovy', 'Domažlice', 'Rokycany', 'Tachov'], 'neighbours' => ['karlovarsky-kraj', 'ustecky-kraj', 'stredocesky-kraj', 'jihocesky-kraj']],
        'karlovarsky-kraj' => ['name' => 'Karlovarský kraj', 'cities' => ['Karlovy Vary', 'Cheb', 'Sokolov'], 'neighbours' => ['plzensky-kraj', 'ustecky-kraj']],
        'ustecky-kraj' => ['name' => 'Ústecký kraj', 'cities' => ['Ústí nad Labem', 'Děčín', 'Teplice', 'Most', 'Chomutov', 'Litoměřice', 'Louny'], 'neighbours' => ['karlovarsky-kraj', 'plzensky-kraj', 'stredocesky-kraj', 'liberecky-kraj']],
        'liberecky-kraj' => ['name' => 'Liberecký kraj', 'cities' => ['Liberec', 'Jablonec nad Nisou', 'Česká Lípa', 'Semily'], 'neighbours' => ['ustecky-kraj', 'stredocesky-kraj', 'kralovehradecky-kraj']],
        'kralovehradecky-kraj' => ['name' => 'Královéhradecký kraj', 'cities' => ['Hradec Králové', 'Trutnov', 'Náchod', 'Jičín', 'Rychnov nad Kněžnou'], 'neighbours' => ['liberecky-kraj', 'stredocesky-kraj', 'pardubicky-kraj']],
        'pardubicky-kraj' => ['name' => 'Pardubický kraj', 'cities' => ['Pardubice', 'Chrudim', 'Svitavy', 'Ústí nad Orlicí'], 'neighbours' => ['kralovehradecky-kraj', 'stredocesky-kraj', 'kraj-vysocina', 'jihomoravsky-kraj', 'olomoucky-kraj']],
        'kraj-vysocina' => ['name' => 'Kraj Vysočina', 'cities' => ['Jihlava', 'Třebíč', 'Havlíčkův Brod', 'Žďár nad Sázavou', 'Pelhřimov'], 'neighbours' => ['stredocesky-kraj', 'jihocesky-kraj', 'pardubicky-kraj', 'jihomoravsky-kraj']],
        'jihomoravsky-kraj' => ['name' => 'Jihomoravský kraj', 'cities' => ['Brno', 'Znojmo', 'Břeclav', 'Hodonín', 'Vyškov', 'Blansko'], 'neighbours' => ['jihocesky-kraj', 'kraj-vysocina', 'pardubicky-kraj', 'olomoucky-kraj', 'zlinsky-kraj']],
        'olomoucky-kraj' => ['name' => 'Olomoucký kraj', 'cities' => ['Olomouc', 'Prostějov', 'Přerov', 'Šumperk', 'Jeseník'], 'neighbours' => ['pardubicky-kraj', 'jihomoravsky-kraj', 'zlinsky-kraj', 'moravskoslezsky-kraj']],
        'zlinsky-kraj' => ['name' => 'Zlínský kraj', 'cities' => ['Zlín', 'Uherské Hradiště', 'Kroměříž', 'Vsetín'], 'neighbours' => ['jihomoravsky-kraj', 'olomoucky-kraj', 'moravskoslezsky-kraj']],
        'moravskoslezsky-kraj' => ['name' => 'Moravskoslezský kraj', 'cities' => ['Ostrava', 'Opava', 'Frýdek-Místek', 'Karviná', 'Nový Jičín', 'Bruntál'], 'neighbours' => ['olomoucky-kraj', 'zlinsky-kraj']],
    ],

    // Prague: administrative districts Praha 1–22 and well-known čtvrti (matched on listings.district).
    'praha_districts' => array_merge(
        array_map(fn ($i) => 'Praha '.$i, range(1, 22)),
        ['Vinohrady', 'Žižkov', 'Karlín', 'Holešovice', 'Smíchov', 'Dejvice', 'Nusle', 'Libeň', 'Vršovice', 'Břevnov', 'Letná', 'Strašnice', 'Bubeneč', 'Michle', 'Podolí', 'Krč', 'Stodůlky', 'Chodov', 'Prosek', 'Vysočany']
    ),
];
