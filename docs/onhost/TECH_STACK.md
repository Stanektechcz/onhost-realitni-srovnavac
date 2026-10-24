# Technologický stack a instalace

Laravel 13/PHP8.3, Blade, Alpine, Tailwind4, Pest

**Stav:** existing_code.

**Evidence:** config/onhost.php;routes/web.php;resources/css/app.css.

## Bezpečný lokální postup

```bash
composer install
npm install
php artisan test
npm run build
```

Používat skutečné dependency lockfiles a aktuální manifesty. Jestliže repozitář nemá implementaci, předložené technologie jsou pouze návrhem, nikoli použitelným instalátorem.
