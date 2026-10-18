# ONhost Reality Research – bezpečný scraper adaptér

Vlastní implementace v Pythonu 3 (stdlib + `requests`). Výstup: JSONL, jeden řádek = jedna nabídka.

## Atribuce a licence
Inspirováno projektem **janchaloupka/web-scraper-nabidek-pronajmu** (Jan Chaloupka,
https://github.com/janchaloupka/web-scraper-nabidek-pronajmu, commit `2f04720`).
Repozitář **nemá žádnou licenci** → platí „všechna práva vyhrazena“. Proto z něj **není převzat
žádný kód**, jen nechráněné myšlenky (seznam portálů, typ přístupu API vs. HTML). Tento kód je samostatné dílo.

## Bezpečnostní pravidla zabudovaná v kódu (stav 2026-10-18)
- Adaptéry jen pro portály, které prošly `../05-kontrola-podminek-portalu.md`: bezrealitky, idnes, eurobydleni.
  Sreality, REALCITY, UlovDomov, REMAX, realingo, BRAVIS jsou v kódu zakázány (`FORBIDDEN`).
- Běh vyžaduje: `enabled: true` v `config.json`, v rozhodnutí řádky `VERDIKT: POVOLENO` **a** `SCHVÁLENO ADVOKÁTEM:`,
  a čas 01–05 h Europe/Prague.
- robots.txt (urllib.robotparser) před každým požadavkem, `Crawl-delay`; nedostupný robots = zákaz.
- 1 požadavek / 10 s / doménu (nelze snížit), max 500/den/portál, `--budget` (výchozí 300) celkem.
- Stop domény při 401/403/429 nebo stránce s CAPTCHA/Cloudflare challenge; žádné přesměrování, proxy ani přihlášení.
- UA: `onhost-research/0.1 (+https://reality.onhost.cz/bot; mailto:info@onhost.cz)`.
- Jen nabídky ≤ 30 dní; žádné URL, texty, fotky, jména, telefony, e-maily.
- Parsery idnes/eurobydleni nejsou ověřené na reálné odpovědi → záměrně nic nestahují (`NotVerifiedAdapter`).

## Výstupní pole (JSONL pro `php artisan reality:import`)
`source, source_id (SHA-256 hash), offer_type (rent|sale), property_type, disposition, area_m2, price_czk,
city, district, lat, lng (3 des. místa), penb, published_at`

## Retence
Surová JSONL data smazat nejpozději 7 dní od stažení (zapsat datum smazání do `../data/README.md`). Data se necommitují.

## Použití
```
python3 reality_scraper.py --dry-run
python3 reality_scraper.py --type both --max-pages 1 --budget 300 --out ../data/agregace-RRRR-MM-DD.jsonl
```
