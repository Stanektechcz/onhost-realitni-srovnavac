<?php

namespace App\Support;

/**
 * Locality data from open/official sources as LINK-OUTS only (no embedding, no scraping, no runtime API calls).
 * Terms checked 2026-10-20:
 *  - ČÚZK Nahlížení do KN: free public application; deep link by S-JTSK coordinates (MapaIdentifikace.aspx).
 *  - MŽP CDS – Mapy povodňového nebezpečí a rizik (cds.mzp.cz): no reuse licence published -> link-out only.
 *  - MZd strategické hlukové mapy 2022 (geoportal.mzcr.cz/shm): no reuse licence published -> link-out only.
 *  - OpenStreetMap (ODbL): linking to openstreetmap.org at coordinates is allowed; we never query Overpass.
 *  - MŠMT Rejstřík škol a školských zařízení: public register -> link-out.
 */
class LocalityLinks
{
    /** @return list<array{key: string, title: string, desc: string, url: string, source: string}> */
    public static function for(?float $lat, ?float $lng, ?string $place = null): array
    {
        $has = $lat !== null && $lng !== null && $lat > 48.4 && $lat < 51.2 && $lng > 12 && $lng < 19;
        $q = rawurlencode((string) $place);
        $links = [];

        if ($has) {
            $j = Sjtsk::fromWgs84($lat, $lng);
            $links[] = ['key' => 'katastr', 'title' => 'Katastrální mapa a parcely', 'desc' => 'Parcely, vlastnictví a zápisy v katastru v okolí místa.',
                'url' => 'https://nahlizenidokn.cuzk.gov.cz/MapaIdentifikace.aspx?l=KN&x='.(int) round($j['x']).'&y='.(int) round($j['y']), 'source' => 'ČÚZK – Nahlížení do katastru nemovitostí'];
        } else {
            $links[] = ['key' => 'katastr', 'title' => 'Katastrální mapa a parcely', 'desc' => 'Vyhledejte parcelu nebo budovu podle adresy.',
                'url' => 'https://nahlizenidokn.cuzk.gov.cz/', 'source' => 'ČÚZK – Nahlížení do katastru nemovitostí'];
        }
        $links[] = ['key' => 'povodne', 'title' => 'Povodňové mapy', 'desc' => 'Záplavová území a mapy povodňového nebezpečí a rizik (Q5, Q20, Q100).',
            'url' => 'https://cds.mzp.cz/', 'source' => 'MŽP / ČHMÚ – Mapy povodňového nebezpečí a povodňových rizik'];
        $links[] = ['key' => 'hluk', 'title' => 'Hlukové mapy', 'desc' => 'Strategické hlukové mapy 2022 (silnice, železnice, letiště, aglomerace).',
            'url' => 'https://geoportal.mzcr.cz/shm/?locale=cs', 'source' => 'Ministerstvo zdravotnictví – Strategické hlukové mapy'];
        $links[] = ['key' => 'doprava', 'title' => 'Zastávky MHD a okolí', 'desc' => 'Mapa s vrstvou veřejné dopravy.',
            'url' => $has ? 'https://www.openstreetmap.org/?mlat='.round($lat, 5).'&mlon='.round($lng, 5).'#map=16/'.round($lat, 5).'/'.round($lng, 5).'&layers=T' : 'https://www.openstreetmap.org/search?query='.$q,
            'source' => '© přispěvatelé OpenStreetMap (ODbL)'];
        $links[] = ['key' => 'skoly', 'title' => 'Školy a školky', 'desc' => 'Oficiální rejstřík škol a školských zařízení.',
            'url' => 'https://rejstriky.msmt.cz/', 'source' => 'MŠMT – Rejstřík škol a školských zařízení'];

        return $links;
    }
}
