#!/usr/bin/env bash
# Deploy ONhost Reality on aaPanel. Run ON THE SERVER as the site user from the app root:
#   bash deploy/aapanel/deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
PHP="${PHP:-php}"            # e.g. /www/server/php/83/bin/php
COMPOSER="${COMPOSER:-composer}"
BRANCH="${BRANCH:-main}"

cd "$APP_DIR"
[ -f .env ] || { echo "Chybí .env (zkopírujte .env.example a vyplňte)"; exit 1; }

$PHP artisan down --retry=30 || true
trap '$PHP artisan up' EXIT

git fetch --prune origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

$COMPOSER install --no-dev --prefer-dist --no-interaction --optimize-autoloader
$PHP artisan migrate --force

npm ci --no-audit --no-fund
npm run build

$PHP artisan storage:link || true
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan queue:restart

echo "Deploy OK: $(git rev-parse --short HEAD)"
