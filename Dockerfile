# syntax=docker/dockerfile:1.7
# ============================================================================
# Order app — Laravel 12 production image
# Multi-stage build:
#   1. composer  → install PHP deps
#   2. node      → build Vite assets
#   3. final     → PHP-FPM 8.3 alpine, slim runtime
# ============================================================================

# ---------- Stage 1: composer ----------
FROM composer:2.7 AS composer-deps

WORKDIR /app

# Install dengan flag yang cocok untuk image production:
# --no-dev: skip dev dependencies (phpunit, pest, dll)
# --optimize-autoloader: PSR-4 classmap, faster autoload
# --no-scripts: skip post-install hooks (akan jalan di entrypoint)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-progress \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---------- Stage 2: node (Vite assets) ----------
FROM node:20-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Salin source yang dibutuhkan Vite (blade templates di-scan untuk Tailwind/etc)
COPY resources resources
COPY public public
COPY vite.config.js ./
RUN npm run build

# ---------- Stage 3: final runtime ----------
FROM php:8.3-fpm-alpine AS runtime

# Install ekstensi PHP yang dibutuhkan Laravel + project ini.
# - pdo_mysql + pdo_sqlite: dual support (kita migrate ke MySQL tapi SQLite tetap fallback)
# - gd, intl, zip, bcmath, opcache: Laravel standard
# - exif: phpoffice/phpspreadsheet butuh
# - sodium: encryption
RUN apk add --no-cache \
        git \
        curl \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        icu-libs \
        oniguruma \
        sqlite-libs \
        tini \
    && apk add --no-cache --virtual .build-deps \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        gd \
        intl \
        zip \
        bcmath \
        exif \
        opcache \
    && apk del --no-network .build-deps

# Create non-root user yang sama UID/GID di semua container (app + scheduler)
ARG WWW_USER=www-data
RUN set -eux; \
    # www-data sudah ada di alpine php-fpm image (uid 82). Pastikan id konsisten.
    id "$WWW_USER" >/dev/null 2>&1 || adduser -D -u 82 -s /sbin/nologin "$WWW_USER"

WORKDIR /var/www/html

# Copy app source (kecuali yang di .dockerignore)
COPY --chown=${WWW_USER}:${WWW_USER} . .

# Copy vendor dari stage composer
COPY --from=composer-deps --chown=${WWW_USER}:${WWW_USER} /app/vendor ./vendor

# Copy built assets dari stage node
COPY --from=node-build --chown=${WWW_USER}:${WWW_USER} /app/public/build ./public/build

# Custom php.ini untuk production
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Custom php-fpm pool config
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Entrypoint untuk run migrate, storage:link, cache, lalu start php-fpm
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permission untuk Laravel writable dirs
RUN set -eux; \
    mkdir -p storage/app/public storage/framework/{cache,sessions,views,testing} storage/logs bootstrap/cache; \
    chown -R ${WWW_USER}:${WWW_USER} storage bootstrap/cache; \
    chmod -R 775 storage bootstrap/cache

USER ${WWW_USER}

# php-fpm listen di 9000 (default), nginx connect via shared network
EXPOSE 9000

# tini = proper PID 1, handle SIGTERM/SIGINT graceful shutdown
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
