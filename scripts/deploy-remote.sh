#!/usr/bin/env bash
set -euo pipefail

# Executado no servidor após o rsync do GitHub Actions.
# Ajuste APP_DIR se o path no servidor for diferente do DEPLOY_PATH do secret.

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$APP_DIR"

echo "==> Deploy em $APP_DIR"

echo "==> Composer (API)"
cd "$APP_DIR/api"
mkdir -p \
  bootstrap/cache \
  storage/app/public \
  storage/framework/{cache,sessions,views} \
  storage/logs
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force --no-interaction
# Bancos dos tenants (stancl): migrations em database/migrations/tenant/
php artisan tenants:migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true

echo "==> Front estático"
# O build já veio no rsync (front/dist). Servir via Nginx/Apache apontando
# document root do site para front/dist (ou copiar para o path público).
if [[ -d "$APP_DIR/front/dist" ]]; then
  echo "front/dist pronto ($(du -sh front/dist | cut -f1))"
fi

echo "==> Reload PHP-FPM (se existir)"
if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl reload php8.3-fpm 2>/dev/null \
    || sudo systemctl reload php8.2-fpm 2>/dev/null \
    || sudo systemctl reload php-fpm 2>/dev/null \
    || true
fi

echo "==> Deploy concluído"
