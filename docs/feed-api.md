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
`available_from` (YYYY-MM-DD), `photos` (pole až `reality.max_photos` (20) adres `https://`, v pořadí zobrazení).

```json
{"listings": [{"external_id": "RK-1042", "offer_type": "rent", "property_type": "flat", "disposition": "2+kk",
  "area_m2": 54, "price_czk": 24000, "fees_czk": 3500, "city": "Praha", "district": "Vinohrady", "penb": "C",
  "description": "Světlý byt po rekonstrukci, 3 minuty od metra."}]}
```

Odpověď: `{"created": 1, "updated": 0, "errors": [{"index": 1, "external_id": "RK-7", "messages": ["..."]}]}`.
Změněná nabídka se u kanceláře bez `--autopublish` vrací do moderace.

## Fotografie
`photos` je seřazený seznam URL. Stahují se na pozadí (fronta), ne během požadavku; výsledek je vidět na webu
po zpracování. Pokud se seznam nezmění, nic se znovu nestahuje; při změně se stáhnou jen nové adresy, zachované
se jen přeřadí a vynechané se smažou. Pole `photos` neposlané = fotky beze změny, `[]` = smazat feedové fotky.

Podmínky (jinak se fotka tiše přeskočí): jen `https://` na portu 443, server musí mít veřejnou IP adresu
(neveřejné, loopback, link-local a rezervované rozsahy IPv4/IPv6 jsou odmítnuty), bez přesměrování (odpověď
musí být přímo 200), limit 15 s, max. `reality.photo.max_kb` (8 MB), JPEG/PNG/WebP min. 400×300 px.

## Zatím chybí
Příjem XML exportů z Realman / Software Real.
