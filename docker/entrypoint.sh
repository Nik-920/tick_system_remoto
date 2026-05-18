#!/bin/sh

# Eliminar configs default de Nginx
rm -f /etc/nginx/conf.d/default.conf || true
rm -f /etc/nginx/conf.d/default || true
rm -f /etc/nginx/sites-enabled/default || true
rm -f /etc/nginx/sites-available/default || true

# Sobrescribir default con vacío si aún existe
echo "" > /etc/nginx/sites-available/default 2>/dev/null || true

cd /app

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
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

echo "=== NGINX CONFIGS ==="
ls -la /etc/nginx/conf.d/ || true
ls -la /etc/nginx/sites-enabled/ || true
nginx -t || true

php-fpm -D
exec nginx -g "daemon off;"
