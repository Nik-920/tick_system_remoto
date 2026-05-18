# ═══════════════════════════════════════════════════════════
# STAGE 1 — Node Builder
# ═══════════════════════════════════════════════════════════
FROM node:22-alpine@sha256:968df39aedcea65eeb078fb336ed7191baf48f972b4479711397108be0966920 AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --prefer-offline

COPY resources/ ./resources/
COPY public/     ./public/
COPY vite.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js*  ./

RUN npm run build

# ═══════════════════════════════════════════════════════════
# STAGE 2 — PHP-FPM Runtime
# ═══════════════════════════════════════════════════════════
FROM php:8.2-fpm@sha256:61f68255ebab17fa34822c6130ba98f392418eebf4fece1856f0d2702bfd3076

ENV APP_ENV=production \
    APP_DEBUG=false

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
    libcap2-bin \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Eliminar nginx default config ANTES de cambiar permisos (somos root aquí)
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default

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

RUN echo "opcache.enable=1"                >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.memory_consumption=128"  >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.validate_timestamps=0"   >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.save_comments=1"         >> /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-scripts

COPY . .
COPY --from=node-builder /app/public/build ./public/build

# OWASP/SonarCloud: Permisos mínimos de Nginx y Laravel para ejecutar sin root
RUN setcap 'cap_net_bind_service=+ep' /usr/sbin/nginx \
    && chown -R www-data:www-data /app /var/log/nginx /var/lib/nginx /run \
    && chmod -R 775 /app/storage /app/bootstrap/cache /var/log/nginx /var/lib/nginx /run

# ── Nginx config ───────────────────────────────────────────
COPY nginx.conf /etc/nginx/conf.d/laravel.conf

# ── Entrypoint ────────────────────────────────────────────
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

# OWASP/SonarCloud: Evitar correr el contenedor como root
USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
