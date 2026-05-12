# ═══════════════════════════════════════════════════════════
# STAGE 1 — Node Builder (compila assets Tailwind/Vite)
# ═══════════════════════════════════════════════════════════
FROM node:22-alpine AS node-builder

WORKDIR /app

# Copiar manifiestos primero → mejor caché de capas
COPY package.json package-lock.json ./

# FIX 1: npm ci en vez de npm install → build reproducible y más rápido
RUN npm ci --prefer-offline

# Copiar todo lo necesario para que Vite compile correctamente
COPY resources/ ./resources/
COPY public/     ./public/
COPY vite.config.js ./

# FIX 2: copiar archivos de configuración de Tailwind/PostCSS
#         (si no existen en tu repo, estas líneas no rompen el build)
COPY tailwind.config.js* ./
COPY postcss.config.js*  ./

RUN npm run build

# ═══════════════════════════════════════════════════════════
# STAGE 2 — PHP-FPM Runtime (imagen de producción final)
# ═══════════════════════════════════════════════════════════
FROM php:8.2-fpm

# FIX 5: indicar a Laravel que estamos en producción
ENV APP_ENV=production \
    APP_DEBUG=false

# ── Dependencias del sistema ──────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    wget \
    nginx \
    && rm -rf /var/lib/apt/lists/*

# ── Extensiones PHP ───────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        gd \
        bcmath \
        mbstring \
        xml \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis opcache

# Configuración de OPcache para producción
RUN echo "opcache.enable=1"                >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.memory_consumption=128"  >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.validate_timestamps=0"   >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.save_comments=1"         >> /usr/local/etc/php/conf.d/opcache.ini

# ── Composer ──────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ── Dependencias PHP (caché separada del código fuente) ───
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-scripts   # Los scripts de artisan van en el entrypoint

# ── Código fuente ─────────────────────────────────────────
COPY . .

# Traer assets compilados del stage de Node
COPY --from=node-builder /app/public/build ./public/build

# ── Permisos ──────────────────────────────────────────────
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

#   Los cache:* definitivos los hace el entrypoint cuando ya tiene el .env real

# ── Nginx config ───────────────────────────────────────────
COPY nginx.conf /etc/nginx/conf.d/default.conf

# ── Entrypoint ────────────────────────────────────────────
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]