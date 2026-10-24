# Kvalita, testy a Definition of Done

| Druh | Výstup |
|---|---|
| Unit | doménové invarianty a validace |
| Integration | provider API, fake, timeout a chybové stavy |
| Authorization | cizí tenant, role, scope a explicitní deny |
| E2E | celá prioritní zákaznická cesta |
| Performance | p95 odpovědí, fronta, závislosti |
| Accessibility | mobil, klávesnice, formuláře, kontrast |
| Security | dependency audit, SSRF, injection, secret scan |
| Recovery | bezpečný retry, restore a rollback |

**Projektové testy/setup:** ```bash
composer install
npm install
php artisan test
npm run build
```

Nevydávat seznam příkazů za proběhlé testy. Do PR vložit skutečný log/CI run a výsledky.
