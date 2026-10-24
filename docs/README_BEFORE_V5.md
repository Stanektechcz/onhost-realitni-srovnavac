# ONhost Reality – MVP realitního portálu

Laravel 13 · Blade + Alpine.js · Tailwind 4 (Vite) · Pest · SQLite (dev/test) / MariaDB (produkce).

## Lokálně
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed   # vypíše heslo admin@example.test
php artisan storage:link && npm run build
php artisan serve
php artisan test
```

## Co je uvnitř
- Inzeráty (pronájem/prodej, typy, dispozice, ceny v haléřích, PENB A–G / „nevydán“ s upozorněním), průvodce v 5 krocích, fotky → WebP (+AVIF pokud GD umí), bez EXIF.
- Vyhledávání s filtry, řazením, stránkováním; mapa MapLibre GL + OSM (načítá se až na vyžádání).
- Oblíbené, hlídací pes (`SendSavedSearchAlerts`, denně), kontakt přes portál (maskované e-maily, honeypot, rate limit), DSA nahlášení + moderace s odůvodněním, audit log.
- Členství/platby/provize za přepínači v `config/reality.php` (výchozí vypnuto); platby jen přes `StubPaymentProvider`.
- `php artisan reality:import file.jsonl` – data ze scraperu jako `source=imported`, jen interní statistika v adminu.
- SEO: sitemap.xml, robots.txt, meta/OG, canonical, schema.org `RealEstateListing`.
- Nasazení: `deploy/aapanel/README.md`.

## Kredit ONhost (za přepínačem, výchozí vypnuto)
Kredit ONhost je peněženka použitelná ve všech projektech ONhost. Portál ho nabízí jako další způsob platby
„Zaplatit kreditem ONhost“ u placeného členství (`/clenstvi`); zůstatek je vidět na ceníku a v „Můj účet“.
S `ONHOST_CREDIT_ENABLED=false` neexistuje žádná trasa, tlačítko ani HTTP volání.

- **Přihlášení:** portál má vlastní přihlášení e-mailem; ONhost ID slouží jen k *propojení účtu* (`/onhost/propojit`,
  server-side authorization code + PKCE, důvěrný klient, `client_secret_post`). Scope `openid email wallet:charge`
  (scope `wallet:charge` jen při zapnutém přepínači); organizaci, ze které se platí, volí uživatel na souhlasné stránce ONhost ID.
  Access/refresh token je jen na serveru v `onhost_connections` (šifrováno `APP_KEY`), obnovuje se automaticky.
- **Platba:** `POST /v1/wallet/ecosystem/charges`, idempotency key `reality-payment-<id platby>` (opakování po výpadku sítě
  použije stejný klíč), `external_reference` = totéž, rodina `listing`, `allow_bonus` dle `ONHOST_CREDIT_ALLOW_BONUS`
  (zakoupený kredit platí 100 %, bonus jen část – rozhoduje ONhost). Úspěch: platba `paid`, `provider_ref` = ONhost
  `transaction_id`, `meta.onhost_charge_id`. Chybové kódy ONhost (402, 409, 422, 429, 401, 403) → české hlášky (`OnhostCreditException`).
- **Vrácení / zrušení:** `POST /admin/platby/{payment}/vratit-kredit` (admin, potvrzení hesla) volá reversal celé platby
  s klíčem `reality-payment-<id>-reversal`, platbu označí `refunded` a členství `cancelled`. Pro budoucí placené služby
  (topování inzerátů apod.) použijte `OnhostCreditPaymentProvider::create()` / `reverse()` stejně.

**Zapnutí:** 1) v ONhost ID zaregistrujte důvěrnou aplikaci (customer) s redirect URI `https://<portál>/onhost/callback`;
2) v ONhost control plane přidejte do `ONHOST_WALLET_ECOSYSTEM_APPS` položku `<client_id>:listing` a zapněte
`ONHOST_WALLET_ECOSYSTEM_ENABLED`; 3) zde nastavte `ONHOST_ID_ISSUER`, `ONHOST_ID_CLIENT_ID`, `ONHOST_ID_CLIENT_SECRET`
(případně `ONHOST_API_URL`, výchozí `<issuer>/v1`), `REALITY_FEATURE_MEMBERSHIPS=true` a `ONHOST_CREDIT_ENABLED=true`;
4) `php artisan migrate`. Rodina `listing` odpovídá katalogu portálu (placené služby inzerentů/hledajících).
