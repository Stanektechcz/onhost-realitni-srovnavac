# Napojení na ekosystém ONhost

Reality fungují **plně samostatně**. Napojení na ekosystém ONhost je volitelné a každá část má vlastní
příznak, který je ve výchozím stavu **vypnutý** (trasa pak vrací 404 a neodchází žádné HTTP volání).
Fakturující subjekt: Adrian Staněk, OSVČ, IČO 08094616.

| Část | Příznak | Trasa |
|---|---|---|
| P1 katalog nabídek | `ECOSYSTEM_CATALOG_ENABLED` | `GET /ecosystem/catalog.json` |
| P7 zdraví | `ECOSYSTEM_HEALTH_ENABLED` | `GET /health` |
| P3 sběrnice | `ECOSYSTEM_BUS_ENABLED` | `POST /bus/webhook` + odchozí události |

Konfigurace: `config/ecosystem.php`, proměnné v `.env.example`.

## P1 – katalog

Formát `onhost-ecosystem-catalog/1`, nejvýše 50 nabídek, bez osobních údajů. Ceny se berou jen z vlastních dat:

* `reality-listing` – vložení inzerátu, `price_minor: null` (vlastní cena neexistuje, nevymýšlíme ji).
* `reality-plan-<kód>` – aktivní členství (`MembershipPlan`, cena v haléřích), **jen pokud je zapnuto**
  `REALITY_FEATURE_MEMBERSHIPS` (jinak by objednávková stránka vracela 404). Perioda: `period_days >= 365` → `year`, jinak `month`.

`order_path` je relativní cesta s `?offer=…&source=onhost-id`. Middleware `RememberEcosystemReferral` si původ
uloží do session (`ecosystem.referral`); dál běží běžný tok aplikace a ONhost ID je volitelné.

## P7 – zdraví

`{"project":"reality","status":"ok|degraded|down","checks":{"database":{"ok":true,"ms":1},"cache":{...}}}`.
Databáze je kritická (selhání → `down`, HTTP 503), cache ne (`degraded`, HTTP 200). Chybové zprávy, hostitelé
ani verze se nevypisují. Laravelí `/up` zůstává beze změny.

## P3 – sběrnice

**Příjem** `POST /bus/webhook`: hlavička `X-Onhost-Signature: t=<unix>,v1=<hex HMAC-SHA256 z "t.tělo">`,
okno 300 s, více tajemství při rotaci (`ECOSYSTEM_BUS_SECRETS=aktuální,předchozí`). Neplatný/chybějící/prošlý
podpis → 401, vadná obálka → 422. Zpracování je idempotentní podle `id` (cache 7 dní, opakování → `duplicate`).
Do logu jde jen typ zprávy, nikdy obsah.

**Odesílání** (`ListingBusPublisher`, `ListingObserver`): podepsané obálky `listing.created.v1` a
`listing.removed.v1` (`sold|withdrawn|moderated|expired`) na `ECOSYSTEM_BUS_SUBSCRIBER_URL` – jen `https://`
a jen při zapnutém příznaku. Odesílají se výhradně inzeráty vyhovující `Listing::public()`; **importované
inzeráty (`source=imported`) nikdy**. Data obsahují jen veřejná pole (`listing_id` = slug, kategorie, titulek,
lokalita, cena, měna, URL) – žádný kontakt na inzerenta, `personal_data: false`.

Přechody: schválení → `created`; zamítnutí/stažení moderací → `removed/moderated`; smazání nebo vrácení do
moderace → `removed/withdrawn`; job `ExpireListings` → `removed/expired`.

## Testy

`tests/Feature/EcosystemTest.php` (vypnuté příznaky, formát katalogu, zdraví 200/503, podpisy včetně rotace
a prošlého času, idempotence, odchozí události, nikdy importované inzeráty, odmítnutí ne-https odběratele).
