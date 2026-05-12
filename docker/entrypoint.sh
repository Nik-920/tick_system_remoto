#!/bin/sh
set -e
cd /app

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

# Limpiar cachés siempre al iniciar
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

php-fpm -D
exec nginx -g "daemon off;"