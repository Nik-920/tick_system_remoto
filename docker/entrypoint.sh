#!/bin/sh

rm -f /etc/nginx/conf.d/default.conf || true
rm -f /etc/nginx/conf.d/default || true
rm -f /etc/nginx/sites-enabled/default || true
rm -f /etc/nginx/sites-available/default || true

cd /app

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

# Generar APP_KEY si está vacío
if grep -q "^APP_KEY=$" .env 2>/dev/null; then
    php artisan key:generate --force
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear

if [ "${CACHE_CONFIG:-false}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

echo "=== APP ENV ==="
php artisan env 2>&1 || true

echo "=== LARAVEL ROUTES ==="
php artisan route:list --path=metrics 2>&1 || true

echo "=== NGINX CONFIGS ==="
ls -la /etc/nginx/conf.d/ || true
ls -la /etc/nginx/sites-enabled/ || true
nginx -t || true

php-fpm -D
sleep 1

exec nginx -g "daemon off;"
