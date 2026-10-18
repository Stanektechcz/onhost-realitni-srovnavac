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
