# Nasazení na aaPanel – reality.onhost.cz

Postup pro Adriana. **Nic z toho se nespouští automaticky** – vše dělá člověk na serveru.

## 1. Web
1. aaPanel → **Website → Add site**: doména `reality.onhost.cz`, PHP **8.3** (nebo novější), bez FTP.
2. Site → **Site directory**: Running directory (document root) = **`/public`**. Vypnout „Anti-XSS“ (open_basedir) jen pokud dělá problémy s `storage/`.
3. Site → **URL rewrite**: zvolit šablonu **laravel5** (try_files $uri $uri/ /index.php?$query_string).
4. PHP 8.3 → Extensions: `pdo_mysql`, `mbstring`, `intl`, `gd` (s WebP; AVIF volitelně), `exif`, `fileinfo`, `bcmath`, `opcache`. V Disabled functions povolit `proc_open`, `putenv` (composer/artisan).
5. Upload limit: `upload_max_filesize = 10M`, `post_max_size = 100M`.

## 2. Databáze
aaPanel → **Databases → Add**: název `reality`, uživatel `reality`, silné heslo, přístup jen `127.0.0.1`. Kódování `utf8mb4`.

## 3. Kód a .env
```bash
cd /www/wwwroot/reality.onhost.cz
git clone <repo-url> .          # nebo git init + remote, prázdný adresář
cp .env.example .env
nano .env    # APP_ENV=production, APP_DEBUG=false, APP_URL=https://reality.onhost.cz,
             # DB_CONNECTION=mariadb + DB_*, MAIL_MAILER=log (zatím), QUEUE_CONNECTION=database,
             # SESSION_SECURE_COOKIE=true; REALITY_FEATURE_* nechat false
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
SEED_ADMIN_PASSWORD='…' php artisan db:seed --force   # jen demo/staging! na produkci admina vytvořit ručně
chown -R www:www storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache
```
Další nasazení: `bash deploy/aapanel/deploy.sh` (git pull, composer --no-dev, migrate --force, npm build, cache).

## 4. SSL
Site → **SSL → Let's Encrypt**, ověření souborem, zapnout **Force HTTPS**.

## 5. Cron (scheduler)
aaPanel → **Cron → Add task** → Shell script, každou minutu:
```
cd /www/wwwroot/reality.onhost.cz && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1
```
(Expirace inzerátů každou hodinu, hlídací pes denně v 7:00.)

## 6. Queue worker (Supervisor)
aaPanel → App Store → **Supervisor** → Add daemon, nebo zkopírovat `deploy/aapanel/supervisor-reality-worker.conf`
do `/etc/supervisor/conf.d/` a `supervisorctl reread && supervisorctl update`. Po deployi `php artisan queue:restart` (dělá deploy.sh).

## 7. Kontrola
- `https://reality.onhost.cz/up` → 200, `/sitemap.xml`, `/robots.txt`.
- `storage/logs/laravel.log` bez chyb; e-maily (hlídací pes, dotazy) se zatím jen logují (`MAIL_MAILER=log`).

## Poznámky
- Import ze scraperu: `php artisan reality:import /cesta/data.jsonl` – data jsou jen pro interní statistiku (`/admin/statistiky`), nikdy veřejně.
- Platby: jen stub provider; Comgate se doplní později za kontraktem `App\Services\Payments\PaymentProvider`.
- Právní texty jsou placeholdery „NÁVRH – čeká na advokáta“ – před spuštěním nahradit.
