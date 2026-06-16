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

# Variables VITE_ disponibles en build time para que Vite las compile en los assets
ARG VITE_FIREBASE_API_KEY
ARG VITE_FIREBASE_APP_ID
ARG VITE_FIREBASE_AUTH_DOMAIN
ARG VITE_FIREBASE_MESSAGING_SENDER_ID
ARG VITE_FIREBASE_PROJECT_ID
ARG VITE_FIREBASE_STORAGE_BUCKET
ARG VITE_FIREBASE_VAPID_KEY

ENV VITE_FIREBASE_API_KEY=$VITE_FIREBASE_API_KEY
ENV VITE_FIREBASE_APP_ID=$VITE_FIREBASE_APP_ID
ENV VITE_FIREBASE_AUTH_DOMAIN=$VITE_FIREBASE_AUTH_DOMAIN
ENV VITE_FIREBASE_MESSAGING_SENDER_ID=$VITE_FIREBASE_MESSAGING_SENDER_ID
ENV VITE_FIREBASE_PROJECT_ID=$VITE_FIREBASE_PROJECT_ID
ENV VITE_FIREBASE_STORAGE_BUCKET=$VITE_FIREBASE_STORAGE_BUCKET
ENV VITE_FIREBASE_VAPID_KEY=$VITE_FIREBASE_VAPID_KEY

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

RUN rm -f /etc/nginx/conf.d/default.conf \
    && rm -f /etc/nginx/sites-available/default \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -sf /dev/null /etc/nginx/sites-enabled/default

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

RUN setcap 'cap_net_bind_service=+ep' /usr/sbin/nginx \
    && chown -R www-data:www-data /app /var/log/nginx /var/lib/nginx /run \
    && chmod -R 775 /app/storage /app/bootstrap/cache /var/log/nginx /var/lib/nginx /run

COPY nginx.railway.conf /etc/nginx/conf.d/00-laravel.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]