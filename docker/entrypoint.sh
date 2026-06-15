#!/bin/sh
set -e

cd /app

# Silence git's "dubious ownership" warning when vendor is volume-mounted
git config --global --add safe.directory /app 2>/dev/null || true

# Bootstrap .env when the container starts without one
if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

# Generate APP_KEY if it is missing
if grep -q "^APP_KEY=$" .env 2>/dev/null; then
    php artisan key:generate --force
fi

# Clear stale framework caches every restart so volume-mounted changes are seen
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Re-discover packages; regenerates bootstrap/cache/packages.php + services.php.
# Required in Docker because composer install --no-scripts skips this step.
php artisan package:discover --ansi

# Pre-generate the blade-icons SVG manifest.
# NOTE: blade-ui-kit/blade-icons uses RecursiveDirectoryIterator which, on Docker for
# Windows volumes (CIFS/virtio-fs), only returns a partial directory listing via
# readdir() for large directories (~1950 SVGs).  We use glob() instead, which calls
# stat() per-file and does not suffer from this kernel limitation.
# The manifest is written directly in PHP to avoid the broken scan entirely.
php artisan icons:cache 2>&1 || true   # run first to create the cache file path
php -r "
\$path = '/app/vendor/mallardduck/blade-lucide-icons/resources/svg';
if (!is_dir(\$path)) { exit(0); }
\$files = glob(\$path . '/*.svg') ?: [];
\$icons = array_map(function(\$f) { return substr(basename(\$f), 0, -4); }, \$files);
sort(\$icons);
\$manifest = array('lucide' => array(\$path => \$icons));
file_put_contents('/app/bootstrap/cache/blade-icons.php', '<?php return ' . var_export(\$manifest, true) . ';');
echo 'Lucide manifest regenerated via glob(): ' . count(\$icons) . \" icons\n\";
" 2>&1 || true

# Optionally cache config/routes for production-like performance inside Docker
if [ "${CACHE_CONFIG:-false}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
fi

# Run pending migrations on first boot (set RUN_MIGRATIONS=true in .env)
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# Tell running queue workers to restart gracefully after a redeploy
if [ "${RESTART_QUEUE_WORKERS:-false}" = "true" ]; then
    php artisan queue:restart || true
fi

# ─── Process selection ───────────────────────────────────────────────────────
# • CMD = "php-fpm" (Dockerfile default / Railway standalone):
#   Start PHP-FPM as a background daemon, then hand PID-1 to Nginx so that
#   SIGTERM from Docker properly shuts down the container.
# • CMD = anything else (docker-compose queue service, custom commands):
#   exec the CMD directly so it becomes PID-1 and receives signals correctly.
case "${1:-}" in
    php-fpm)
        php-fpm -D
        sleep 1
        exec nginx -g "daemon off;"
        ;;
    *)
        exec "$@"
        ;;
esac
