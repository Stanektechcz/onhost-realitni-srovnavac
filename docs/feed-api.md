# Importní feed pro realitní kanceláře (API v1)

Kancelář posílá své nabídky ze svého softwaru na reality.onhost.cz. Obsah patří kanceláři a posílá ho se
souhlasem, proto se zobrazuje veřejně pod jejím účtem (na rozdíl od `source=imported`, které je jen interní).

## Přístup
1. Kancelář si založí účet s rolí *Realitní kancelář*.
2. Správce na serveru vydá token: `php artisan reality:feed:token rk@example.cz` (`--autopublish` pro ověřené
   kanceláře bez ruční moderace, `--revoke` token zruší). Token se zobrazí jen jednou, uloží se jen jeho SHA-256.
3. Každý požadavek nese hlavičku `Authorization: Bearer <token>`. Limit 120 požadavků za minutu.

## Endpointy
| Metoda | Cesta | Co dělá |
|---|---|---|
| POST | `/api/feed/v1/listings` | Vloží nebo aktualizuje 1 až 200 nabídek podle `external_id` |
| GET | `/api/feed/v1/listings` | Stav všech nabídek kanceláře (pending, active, rejected s důvodem, expired) |
| DELETE | `/api/feed/v1/listings/{external_id}` | Stáhne nabídku z webu |

## Položka nabídky (JSON)
Povinné: `external_id` (A-Z, 0-9, `.`, `_`, `-`), `description` (min. 30 znaků), `offer_type` (`rent`|`sale`),
`property_type` (`flat`|`house`|`land`|`commercial`|`room`), `price_czk`, `city`, `penb` (`A`–`G` nebo `none`).
Volitelné: `title`, `disposition`, `area_m2`, `fees_czk`, `deposit_czk`, `district`, `street`, `lat`, `lng`,
`furnishing` (`none`|`partial`|`full`), `floor`, `has_elevator`, `has_balcony`, `has_parking`, `pets_allowed`,
`available_from` (YYYY-MM-DD).

```json
{"listings": [{"external_id": "RK-1042", "offer_type": "rent", "property_type": "flat", "disposition": "2+kk",
  "area_m2": 54, "price_czk": 24000, "fees_czk": 3500, "city": "Praha", "district": "Vinohrady", "penb": "C",
  "description": "Světlý byt po rekonstrukci, 3 minuty od metra."}]}
```

Odpověď: `{"created": 1, "updated": 0, "errors": [{"index": 1, "external_id": "RK-7", "messages": ["..."]}]}`.
Změněná nabídka se u kanceláře bez `--autopublish` vrací do moderace.

## Zatím chybí
Fotografie (stahování z URL kanceláře s ochranou proti SSRF) a příjem XML exportů z Realman / Software Real.

## Sreality-kompatibilní XML-RPC import (`/import/RPC2`)

Pro výrobce CRM, kteří už mají konektor na Sreality: stačí změnit URL na
`https://reality.onhost.cz/import/RPC2` a přihlašovací údaje. Protokol XML-RPC, UTF-8, `POST`, limit
300 požadavků za minutu, max. 2 MB na požadavek. Nabídky se ukládají stejně jako u JSON feedu
(`source=internal` pod účtem kanceláře, moderace, pokud nemá `--autopublish`; `advert_rkid` = `external_id`).

### Přístup
- `software_key` vydáváme každému výrobci CRM (env `REALITY_IMPORT_SOFTWARE_KEYS`, více klíčů oddělte čárkou).
- `client_id` (= id účtu kanceláře) a heslo: `php artisan reality:feed:token rk@example.cz --rpc [--autopublish]`.
  Heslo se ukládá šifrovaně (APP_KEY), protože schéma Sreality potřebuje `md5(heslo)` i na serveru.
  `--revoke` zruší token i heslo.

### Relace (jako Sreality)
1. `getHash(client_id)` → `output[0].sessionId` (80 hex znaků).
2. Před každým dalším voláním klient spočítá nové `session_id = sid[0:48] . md5(sid . md5(heslo) . software_key)`,
   kde `sid` je poslední použité (nebo z getHash) session_id.
3. `login(session_id)`, pak volání, na konci `logout(session_id)`. Relace vyprší po 15 min nečinnosti.

Každá odpověď je struct `{status, statusMessage, output}`.

| Metoda | Parametry | Výsledek |
|---|---|---|
| `version()` | – | `output[0].version`, bez přihlášení |
| `getHash` | `client_id` | 200 / 402 neexistující klient |
| `login`, `logout` | `session_id` | 200 / 407 neplatná relace |
| `addAdvert` | `session_id, advert_data` | upsert podle `advert_rkid`; `output[0].advert_id`; 452 chybí/neplatné položky |
| `delAdvert` | `session_id, advert_id, advert_rkid` | stáhne nabídku (podle `advert_id`, jinak `advert_rkid`); 404 |
| `listAdvert` | `session_id` | `output[]` = `{advert_id, advert_rkid, status, advert_url}` (bez stažených) |
| fotky, videa, makléři, projekty, statistiky, poptávky, `topAdvert` | – | 501 nepodporováno (relace se přesto posune) |

### Mapování `advert_data`
| Sreality | Náš údaj |
|---|---|
| `advert_function` 1 Prodej / 2 Pronájem | `offer_type` sale / rent (3 Dražby, 4 Podíly → 452) |
| `advert_type` 1 Byty, 2 Domy, 3 Pozemky, 4 Komerční; `advert_subtype` 47 Pokoj | flat, house, land, commercial; room (5 Ostatní → 452) |
| `advert_subtype` 2–12, 16 u bytů | dispozice 1+kk … 5+1, 6+, atyp |
| `advert_price` (jen `advert_price_currency` 1 = Kč) | cena; jiná měna → 452. `advert_price_unit` se ignoruje |
| `usable_area` (jinak `floor_area`, `estate_area`) | plocha m² |
| `locality_city`, `locality_citypart`, `locality_street` + `locality_cp`/`locality_co`, `locality_latitude/longitude` | město, část, ulice, souřadnice |
| `energy_efficiency_rating` 1–7 | PENB A–G (chybí → `none`) |
| `furnished` 1/2/3 | full / none / partial |
| `floor_number`, `elevator`, `balcony`/`loggia`/`terrace`, `parking_lots`/`garage`, `ready_date` | patro, výtah, balkon, parkování, dostupné od |
| `description` | popis (min. 30 znaků) |

Ostatní položky (RÚIAN, dražby, podíly, makléři, projekty, cizojazyčné texty …) se zatím ignorují.

### Bezpečnost
Vlastní XML-RPC parser: DTD/`<!ENTITY>` se odmítají (fault), `LIBXML_NONET`, bez substituce entit, limit velikosti
a hloubky. Endpoint je vyjmut z CSRF (`bootstrap/app.php`), kanceláře jsou izolované (`source_name = feed:{id}`).
